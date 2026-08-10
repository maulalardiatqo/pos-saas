<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\StockMovement;
use App\Models\Transaction;

class PosSyncController extends Controller
{
    /**
     * Endpoint untuk mendownload Master Data ke Mobile POS (Offline Mode)
     */
    public function pullMasterData(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant ?? $user->outlet->company ?? null;
        
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Perusahaan tidak ditemukan.'], 404);
        }

        $tenantId = $company->id;
        $outletId = $user->outlet_id;

        // =====================================================================
        // 1. SETTINGS TOKO
        // =====================================================================
        $settings = [
            'pos_with_img' => (bool) ($company->pos_with_img ?? true),
            'nota_size' => $company->nota_size ?? '58mm',
            'is_loyalty_enabled' => (bool) ($company->is_loyalty_enabled ?? false),
            'loyalty_spend_amount' => (float) ($company->loyalty_spend_amount ?? 0),
            'loyalty_point_earned' => (int) ($company->loyalty_point_earned ?? 0),
            'loyalty_point_value' => (float) ($company->loyalty_point_value ?? 0),
            'midtrans_client_key' => $company->midtrans_client_key ?? '',
            'midtrans_is_production' => (bool) ($company->midtrans_is_production ?? false),
        ];

        // =====================================================================
        // 2. KATEGORI
        // =====================================================================
        $categories = Category::where('company_id', $tenantId)
            ->select('id', 'name')
            ->get();

        // =====================================================================
        // 3. PELANGGAN (CUSTOMERS)
        // =====================================================================
        $customers = Customer::with('membership:id,name')
            ->where('company_id', $tenantId)
            ->where('is_active', 1)
            ->select('id', 'name', 'points_balance', 'membership_id')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'member' => $c->membership->name ?? '-',
                    'points' => $c->points_balance ?? 0,
                ];
            });

        // =====================================================================
        // 4. HADIAH (LOYALTY REWARDS)
        // =====================================================================
        $rewards = LoyaltyReward::where('company_id', $tenantId)
            ->where('is_active', 1)
            ->select('id', 'name', 'reward_type', 'product_id', 'discount_amount', 'points_required')
            ->get();

        // =====================================================================
        // 5. PRODUK & STOK (Hanya untuk Outlet Kasir yang sedang Login)
        // =====================================================================
        // Subquery untuk mengambil stok riil terakhir di outlet kasir tersebut
        $latestStockSubquery = StockMovement::select('balance_after')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $outletId)
            ->latest('created_at')
            ->limit(1);

        $products = Product::query()
            ->where('products.company_id', $tenantId)
            ->where('products.is_active', 1)
            ->leftJoin('uoms as base_uoms', 'products.base_uom_id', '=', 'base_uoms.id')
            ->select([
                'products.id', 
                'products.name', 
                'products.category_id', 
                'products.item_type', 
                'products.product_type', 
                'products.base_price as price', 
                'products.cost_price as cost', 
                'products.base_uom_id',
                'products.barcode',
                'products.image_url'
            ])
            ->selectSub($latestStockSubquery, 'current_stock')
            ->get();

        // Proses format UOM (Satuan) agar sesuai dengan struktur JSON Flutter
        foreach ($products as $product) {
            $availableUoms = [];
            
            // Satuan Dasar
            $baseUomData = DB::table('uoms')->where('id', $product->base_uom_id)->first();
            if ($baseUomData) {
                $availableUoms[] = [
                    'id' => $baseUomData->id,
                    'name' => $baseUomData->name,
                    'price' => (float) $product->price,
                    'conversion_factor' => 1.00,
                ];
            }

            // Satuan Turunan (Varian Satuan seperti Lusin, Dus, dll)
            $variantUoms = DB::table('product_uoms')
                ->join('uoms', 'product_uoms.uom_id', '=', 'uoms.id')
                ->where('product_uoms.product_id', $product->id)
                ->whereNull('product_uoms.deleted_at')
                ->select('uoms.id', 'uoms.name', 'product_uoms.selling_price as price', 'product_uoms.conversion_factor')
                ->get();

            foreach ($variantUoms as $vu) {
                $availableUoms[] = [
                    'id' => $vu->id,
                    'name' => $vu->name,
                    'price' => (float) $vu->price,
                    'conversion_factor' => (float) $vu->conversion_factor,
                ];
            }
            
            $product->available_uoms = $availableUoms;
        }

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil ditarik',
            'settings' => $settings,
            'categories' => $categories,
            'customers' => $customers,
            'available_rewards' => $rewards,
            'products' => $products,
        ]);
    }
    // =========================================================================
    // PILAR 4: MENERIMA UPLOAD TRANSAKSI OFFLINE DARI MOBILE POS
    // =========================================================================
    public function pushOfflineData(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant ?? $user->outlet->company ?? null;
        
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Perusahaan tidak ditemukan.'], 404);
        }

        $tenantId = $company->id;
        $outletId = $user->outlet_id;
        $transactions = $request->input('transactions', []);

        if (empty($transactions)) {
            return response()->json(['success' => true, 'message' => 'Tidak ada transaksi untuk disinkronkan', 'synced_ids' => []]);
        }

        DB::beginTransaction();
        try {
            $syncedIds = [];

            foreach ($transactions as $trxData) {
                // 1. CEK DUPLIKASI (Mencegah nota offline yang sama masuk 2x)
                $exists = \App\Models\Transaction::where('transaction_number', $trxData['transaction_number'])
                            ->where('company_id', $tenantId)
                            ->exists();

                if ($exists) {
                    $syncedIds[] = $trxData['local_id'];
                    continue; 
                }

                // 2. BUAT HEADER TRANSAKSI
                $newTrx = \App\Models\Transaction::create([
                    'company_id'            => $tenantId,
                    'outlet_id'             => $outletId,
                    'user_id'               => $user->id,
                    'customer_id'           => $trxData['customer_id'] ?? null,
                    'account_id'            => $trxData['account_id'] ?? null, 
                    'transaction_number'    => $trxData['transaction_number'],
                    'type'                  => 'sale', 
                    'in_out'                => 'in', 
                    'status'                => 'completed', 
                    'payment_method'        => $trxData['payment_method'] ?? 'cash',
                    'subtotal'              => $trxData['subtotal'] ?? 0,
                    'discount'              => $trxData['discount'] ?? 0,
                    'points_used'           => $trxData['points_used'] ?? 0,
                    'point_discount_amount' => $trxData['point_discount_amount'] ?? 0,
                    'grand_total'           => $trxData['grand_total'] ?? 0,
                    'amount_paid'           => $trxData['amount_paid'] ?? 0,
                    'amount_change'         => $trxData['amount_change'] ?? 0,
                    'created_at'            => $trxData['created_at'], 
                ]);

                // 3. PROSES ITEM & AMBIL HARGA MODAL DARI TABEL PRODUCT
                $items = $trxData['items'] ?? [];
                
                // Urutkan ID produk untuk mencegah Deadlock
                $productIds = collect($items)->pluck('product_id')->sort()->unique()->toArray();
                $lockedProducts = \App\Models\Product::whereIn('id', $productIds)
                                    ->lockForUpdate()
                                    ->get()
                                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $lockedProducts[$item['product_id']] ?? null;
                    
                    // PERBAIKAN: Ambil cost_price dari server, kalikan dengan konversi satuan (jika ada)
                    $costPrice = $product ? (float) $product->cost_price : 0;
                    $totalCostPrice = $costPrice * ($item['conversion_factor'] ?? 1);

                    \App\Models\TransactionItem::create([
                        'company_id'        => $tenantId,
                        'transaction_id'    => $newTrx->id,
                        'product_id'        => $item['product_id'],
                        'uom_id'            => $item['uom_id'] ?? null,
                        'qty'               => $item['qty'],
                        'conversion_factor' => $item['conversion_factor'] ?? 1,
                        'base_qty'          => $item['qty'] * ($item['conversion_factor'] ?? 1),
                        'cost_price'        => $totalCostPrice, // <-- INI YANG TADI MENGHILANG
                        'selling_price'     => $item['price'] ?? ($product ? $product->base_price : 0),
                        'subtotal'          => $item['subtotal'],
                    ]);

                    // Potong stok (kecuali Jasa)
                    if ($product && $product->item_type !== 'service') {
                        $baseQty = $item['qty'] * ($item['conversion_factor'] ?? 1);
                        
                        $lastMovement = \App\Models\StockMovement::where('product_id', $product->id)
                                            ->where('outlet_id', $outletId)
                                            ->latest('created_at')
                                            ->first();
                                            
                        $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0.00;

                        \App\Models\StockMovement::create([
                            'company_id'     => $tenantId, 
                            'outlet_id'      => $outletId, 
                            'product_id'     => $product->id,
                            'type'           => 'sale', 
                            'reference_type' => \App\Models\Transaction::class, 
                            'reference_id'   => $newTrx->id,
                            'quantity'       => $baseQty,
                            'balance_before' => $balanceBefore,
                            'balance_after'  => $balanceBefore - $baseQty,
                            'remarks'        => 'Sinkronisasi POS Offline: ' . $newTrx->transaction_number,
                            'created_at'     => $newTrx->created_at, 
                        ]);
                    }
                }

                // 4. POTONG POIN PELANGGAN JIKA ADA
                if (($trxData['points_used'] ?? 0) > 0 && $newTrx->customer_id) {
                    \App\Models\PointHistory::create([
                        'company_id'   => $tenantId, 
                        'customer_id'  => $newTrx->customer_id,
                        'type'         => 'redeem', 
                        'amount'       => $trxData['points_used'],
                        'reference_id' => $newTrx->transaction_number, 
                        'description'  => 'Sinkronisasi Penukaran Poin Offline',
                        'created_at'   => $newTrx->created_at,
                    ]);
                }

                // 5. Tambahkan uang ke Saldo Akun (Kas/Bank)
                if ($newTrx->account_id) {
                    \App\Models\Account::where('id', $newTrx->account_id)->increment('balance', $newTrx->grand_total);
                }

                $syncedIds[] = $trxData['local_id'];
            }

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => count($syncedIds) . ' Transaksi berhasil disinkronisasi.',
                'synced_ids' => $syncedIds 
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal sync: ' . $e->getMessage()]);
        }
    }
}