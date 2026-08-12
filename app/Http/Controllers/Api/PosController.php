<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use App\Models\Stock; // <-- IMPORT MODEL STOCK BARU KITA
use App\Models\PointHistory;
use App\Models\PosSession;
use App\Models\Voucher;
use App\Models\LoyaltyReward; 
use App\Services\MidtransService; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    // =========================================================================
    // 1. AMBIL DATA MASTER POS
    // =========================================================================
    public function getData(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant ?? $user->outlet->company ?? null;
        $tenantId = $company?->id;
        $outletId = $user->outlet_id;

        // PERBAIKAN STOK: Membaca langsung dari tabel stocks (Lebih Ringan)
        $latestStockSubquery = Stock::selectRaw('COALESCE(qty, 0)')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $outletId)
            ->limit(1);

        $products = Product::query()
            ->where('products.company_id', $tenantId)
            ->where('products.is_active', 1)
            ->join('uoms as base_uoms', 'products.base_uom_id', '=', 'base_uoms.id')
            ->leftJoin('product_uoms', function ($join) {
                $join->on('products.id', '=', 'product_uoms.product_id')
                     ->where('product_uoms.is_default', true)
                     ->whereNull('product_uoms.deleted_at'); 
            })
            ->leftJoin('uoms as variant_uoms', 'product_uoms.uom_id', '=', 'variant_uoms.id')
            ->select([
                'products.id', 'products.name', 'products.category_id', 'products.image_url',
                'products.item_type', 'products.product_type', 'products.base_price as price', 'products.cost_price as cost', 
                'products.base_uom_id',
                DB::raw('COALESCE(product_uoms.barcode, products.barcode) as barcode'),
                DB::raw('COALESCE(product_uoms.uom_id, products.base_uom_id) as uom_id'),
                DB::raw('COALESCE(variant_uoms.name, base_uoms.name) as uom_name'),
                DB::raw('COALESCE(product_uoms.conversion_factor, 1) as conversion_factor')
            ])
            ->selectSub($latestStockSubquery, 'current_stock')
            ->get();

        foreach ($products as $product) {
            $availableUoms = [];
            
            $baseUomData = DB::table('uoms')->where('id', $product->base_uom_id)->first();
            if ($baseUomData) {
                $availableUoms[] = [
                    'id' => $baseUomData->id,
                    'name' => $baseUomData->name,
                    'price' => (float) $product->price,
                    'conversion_factor' => 1.00,
                ];
            }

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

        $categories = Category::where('company_id', $tenantId)->get(['id', 'name']);
        
        $customers = Customer::with('membership')->where('company_id', $tenantId)->get()->map(function($c) {
            return [
                'id' => $c->id, 'name' => $c->name,
                'member' => $c->membership->name ?? '-', 'points' => $c->points_balance ?? 0,
            ];
        });

       $accounts = Account::with('outlet:id,name')
            ->where('company_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($outletId) {
                $q->where('outlet_id', $outletId)->orWhereNull('outlet_id');
            })->get(['id', 'name', 'payment_methods', 'outlet_id']);

        $availableRewards = LoyaltyReward::with('product')
            ->where('company_id', $tenantId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'settings' => [
                'pos_with_img' => (bool) ($company->pos_with_img ?? true),
                'nota_size' => $company->nota_size ?? '58mm',
                'has_midtrans' => !empty($company->midtrans_merchant_id) || !empty($company->midtrans_server_key),
                'midtrans_client_key' => $company->midtrans_client_key ?? '',
                'midtrans_is_production' => (bool) ($company->midtrans_is_production ?? false),
                'is_loyalty_enabled' => (bool) ($company->is_loyalty_enabled ?? false),
                'loyalty_point_value' => (float) ($company->loyalty_point_value ?? 1),
                'loyalty_spend_amount' => (float) ($company->loyalty_spend_amount ?? 0),
                'loyalty_point_earned' => (int) ($company->loyalty_point_earned ?? 0),
            ],
            'categories' => $categories,
            'products' => $products,
            'customers' => $customers,
            'accounts' => $accounts,
            'available_rewards' => $availableRewards 
        ]);
    }

    // =========================================================================
    // 2. LOGIKA CHECKOUT (DILENGKAPI PESSIMISTIC LOCKING & TABEL STOCKS)
    // =========================================================================
    public function checkout(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant ?? $user->outlet->company ?? null;
        $tenantId = $company?->id;
        $outletId = $user->outlet_id;

        $cart = $request->input('cart', []);
        $paymentMethod = $request->input('payment_method');
        $accountId = $request->input('account_id');
        $grandTotal = (float) $request->input('grand_total', 0);
        
        $claimedRewards = $request->input('claimed_rewards', []);
        $manualPointsUsed = (int) $request->input('manual_points_used', 0);

        if (empty($cart)) return response()->json(['success' => false, 'message' => 'Keranjang kosong!'], 400);
        if (empty($accountId)) return response()->json(['success' => false, 'message' => 'Pilih Rekening Tujuan!'], 400);

        try {
            // [!!! PENTING !!!] Buka transaksi DATABASE di AWAL sebelum mengecek stok
            DB::beginTransaction();

            // --- 1. HITUNG KEBUTUHAN STOK ---
            $requiredStocks = []; 
            foreach ($cart as $item) {
                $isBundle = in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']);
                $isService = ($item['item_type'] ?? 'goods') === 'service';

                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $item['id'])->get();
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        if ($child && $child->item_type === 'goods') {
                            $qtyNeeded = $item['qty'] * ($item['conversion_factor'] ?? 1) * (float)$comp->quantity;
                            $requiredStocks[$child->id] = ($requiredStocks[$child->id] ?? 0) + $qtyNeeded;
                        }
                    }
                } elseif (!$isService && empty($item['is_reward'])) { 
                    $qtyNeeded = $item['qty'] * ($item['conversion_factor'] ?? 1);
                    $requiredStocks[$item['id']] = ($requiredStocks[$item['id']] ?? 0) + $qtyNeeded;
                }
            }

            // --- 2. PESSIMISTIC LOCKING PADA PRODUCT UNTUK MENCEGAH DEADLOCK ---
            if (!empty($requiredStocks)) {
                $productIdsToLock = array_keys($requiredStocks);
                
                // MENGURUTKAN ID SANGAT PENTING: Mencegah 'Deadlock' (Error MySQL) 
                sort($productIdsToLock);

                // Eksekusi Kunci! (Kasir lain yang mau beli produk ini akan 'ngantre' dulu)
                $lockedProducts = Product::whereIn('id', $productIdsToLock)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // --- 3. VALIDASI STOK (Membaca langsung dari tabel stocks) ---
                foreach ($requiredStocks as $prodId => $totalNeeded) {
                    $stockRecord = DB::table('stocks')
                        ->where('product_id', $prodId)
                        ->where('outlet_id', $outletId)
                        ->first();
                        
                    $available = $stockRecord ? (float)$stockRecord->qty : 0;
                    
                    if ($totalNeeded > $available) {
                        $prodName = $lockedProducts[$prodId]->name ?? 'Produk';
                        // Jika stok kurang, batalkan transaksi, lepas gembok otomatis!
                        DB::rollBack(); 
                        return response()->json([
                            'success' => false, 
                            'message' => "Stok kurang! Dibutuhkan {$totalNeeded} pcs untuk {$prodName} (Sisa: {$available})."
                        ], 400);
                    }
                }
            }

            // --- 4. JIKA STOK CUKUP, LANJUT BUAT TRANSAKSI ---
            $uniqueOrderId = 'POS-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $isMidtrans = in_array($paymentMethod, ['qris', 'ewallet']) && !empty($company->midtrans_server_key);

            $activeSession = PosSession::where('user_id', $user->id)->where('outlet_id', $outletId)->where('status', 'open')->first();

            $transaction = Transaction::create([
                'company_id'            => $tenantId,
                'outlet_id'             => $outletId,
                'user_id'               => $user->id,
                'pos_session_id'        => $activeSession ? $activeSession->id : null,
                'customer_id'           => $request->input('customer_id'),
                'account_id'            => $accountId, 
                'transaction_number'    => $uniqueOrderId,
                'type'                  => 'sale', 
                'in_out'                => 'in', 
                'status'                => $isMidtrans ? 'pending' : 'completed', 
                'payment_method'        => $paymentMethod,
                'subtotal'              => $request->input('subtotal', 0),
                'discount'              => $request->input('discount', 0),
                'points_used'           => $request->input('points_used', 0),
                'point_discount_amount' => $request->input('point_discount_amount', 0),
                'grand_total'           => $grandTotal,
                'amount_paid'           => $request->input('amount_paid', $grandTotal),
                'amount_change'         => $request->input('amount_change', 0),
            ]);

            $itemDetails = [];
            foreach ($cart as $item) {
                TransactionItem::create([
                    'company_id'        => $tenantId,
                    'transaction_id'    => $transaction->id,
                    'product_id'        => $item['id'],
                    'uom_id'            => $item['uom_id'] ?? null,
                    'qty'               => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'base_qty'          => $item['qty'] * ($item['conversion_factor'] ?? 1),
                    'cost_price'        => ($item['cost'] ?? 0) * ($item['conversion_factor'] ?? 1), 
                    'selling_price'     => $item['price'],
                    'subtotal'          => $item['price'] * $item['qty'],
                ]);

                if (empty($item['is_reward'])) {
                    $itemDetails[] = [
                        'id' => (string) $item['id'],
                        'price' => (int) preg_replace('/[^0-9]/', '', (string) round($item['price'])),
                        'quantity' => (int) $item['qty'],
                        'name' => substr($item['name'], 0, 50),
                    ];
                }
            }

            // --- REDEEM POINTS ---
            if ($manualPointsUsed > 0 && $transaction->customer_id) {
                PointHistory::create([
                    'company_id' => $tenantId, 'customer_id' => $transaction->customer_id,
                    'type' => 'redeem', 'amount' => $manualPointsUsed,
                    'reference_id' => $transaction->transaction_number, 'description' => 'Tukar poin (Cashback) di Mobile POS',
                ]);
            }
            if (!empty($claimedRewards) && $transaction->customer_id) {
                foreach ($claimedRewards as $reward) {
                    PointHistory::create([
                        'company_id' => $tenantId, 'customer_id' => $transaction->customer_id,
                        'type' => 'redeem', 'amount' => $reward['points'],
                        'reference_id' => $transaction->transaction_number, 'description' => 'Tukar Hadiah: ' . $reward['name'],
                    ]);
                }
            }

            // --- 5. EKSEKUSI PEMBAYARAN KAS / MANUAL ---
            if (!$isMidtrans) {
                $this->fulfillTransaction($transaction, $company);
                DB::commit(); // TRANSAKSI SELESAI, KUNCI (GEMBOK) STOK DILEPASKAN!
                return response()->json([
                    'success' => true,
                    'is_midtrans' => false,
                    'message' => 'Transaksi Berhasil!',
                    'transaction_id' => $transaction->id,
                    'nota_size' => $company->nota_size ?? '58mm'
                ]);
            }

            // --- 6. EKSEKUSI JIKA MENGGUNAKAN MIDTRANS ---
            if ($isMidtrans) {
                $totalDiscount = $request->input('discount', 0) + $request->input('point_discount_amount', 0);
                if ($totalDiscount > 0) {
                    $itemDetails[] = [
                        'id' => 'DISCOUNT', 'price' => -1 * (int) preg_replace('/[^0-9]/', '', (string) round($totalDiscount)),
                        'quantity' => 1, 'name' => 'Total Diskon Nota',
                    ];
                }

                $transactionDetails = ['order_id' => $uniqueOrderId, 'gross_amount' => (int) preg_replace('/[^0-9]/', '', (string) round($grandTotal))];
                $snapToken = MidtransService::createTransaction($company, $transactionDetails, $itemDetails);
                
                DB::commit(); // TRANSAKSI SELESAI, KUNCI (GEMBOK) DILEPASKAN!
                return response()->json([
                    'success' => true,
                    'is_midtrans' => true,
                    'message' => 'Lanjutkan Pembayaran QRIS/E-Wallet',
                    'snap_token' => $snapToken,
                    'transaction_id' => $transaction->id
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack(); // JIKA ADA ERROR SISTEM, BATALKAN SEMUA & LEPAS KUNCI
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. FUNGSI FULFILL (JURNAL, STOK BUNDLE, ADMIN FEE, POIN)
    // =========================================================================
    protected function fulfillTransaction(Transaction $transaction, $company)
    {
        $outletId = $transaction->outlet_id;
        $companyId = $transaction->company_id;

        $adminFee = 0;
        $gross = (float) $transaction->grand_total;
        $method = $transaction->payment_method;

        if (in_array($method, ['qris', 'ewallet'])) {
            $adminFee = $gross * 0.007; 
        } elseif ($method === 'transfer') {
            $adminFee = 4000;
        } elseif ($method === 'credit_card') {
            $adminFee = ($gross * 0.02) + 2000; 
        }

        $adminFee = round($adminFee);
        $netAmount = $gross - $adminFee; 

        if ($adminFee > 0) {
            DB::table('transactions')->where('id', $transaction->id)->update(['admin_fee' => $adminFee]);
        }

        Account::where('id', $transaction->account_id)->increment('balance', $netAmount);

        if ($transaction->pos_session_id) {
            $activeSession = PosSession::find($transaction->pos_session_id);
            if ($activeSession) {
                $activeSession->increment('total_sales', $transaction->grand_total);
                if ($method === 'cash') {
                    $activeSession->increment('total_cash_sales', $transaction->grand_total);
                }
            }
        }

        if ($transaction->customer_id && $company->is_loyalty_enabled && $company->loyalty_spend_amount > 0) {
            $earnedMultiplier = floor($transaction->grand_total / $company->loyalty_spend_amount);
            $earnedPoints = $earnedMultiplier * (int) $company->loyalty_point_earned; 

            if ($earnedPoints > 0) {
                PointHistory::create([
                    'company_id' => $companyId,
                    'customer_id' => $transaction->customer_id,
                    'type' => 'earn',
                    'amount' => $earnedPoints,
                    'reference_id' => $transaction->transaction_number,
                    'description' => 'Earned points from Mobile POS Sale',
                ]);
            }
        }

        $items = TransactionItem::where('transaction_id', $transaction->id)->get();

        foreach ($items as $trxItem) {
            $product = Product::find($trxItem->product_id);
            if (!$product) continue;

            $isService = ($product->item_type === 'service');
            $isBundle = in_array($product->product_type, ['bundle', 'recipe']);

            if ($isBundle) {
                $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                foreach ($components as $comp) {
                    $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                    if ($child && $child->item_type === 'goods') {
                        $qtyToDeduct = $trxItem->base_qty * (float)$comp->quantity;
                        
                        // PEMOTONGAN STOK BUNDLE DENGAN TABEL STOCKS + LOCKING
                        $stockRecord = Stock::firstOrCreate(
                            ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $comp->child_product_id],
                            ['qty' => 0]
                        );
                        $stockRecord->lockForUpdate();
                        
                        $balanceBefore = (float) $stockRecord->qty;
                        $balanceAfter = $balanceBefore - $qtyToDeduct;
                        
                        $stockRecord->update(['qty' => $balanceAfter]);

                        StockMovement::create([
                            'company_id' => $companyId, 'outlet_id' => $outletId,
                            'product_id' => $comp->child_product_id,
                            'type' => 'sale', 
                            'reference_type' => Transaction::class, 'reference_id' => $transaction->id,
                            'quantity' => $qtyToDeduct,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'remarks' => "Terjual dalam Paket/Bundle. Nota: " . $transaction->transaction_number,
                        ]);
                    }
                }
            } elseif (!$isService) {
                
                // PEMOTONGAN STOK STANDAR DENGAN TABEL STOCKS + LOCKING
                $stockRecord = Stock::firstOrCreate(
                    ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id],
                    ['qty' => 0]
                );
                $stockRecord->lockForUpdate();
                
                $balanceBefore = (float) $stockRecord->qty;
                $balanceAfter = $balanceBefore - $trxItem->base_qty;
                
                $stockRecord->update(['qty' => $balanceAfter]);

                StockMovement::create([
                    'company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id,
                    'type' => 'sale', 
                    'reference_type' => Transaction::class, 'reference_id' => $transaction->id,
                    'quantity' => $trxItem->base_qty,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'remarks' => 'Penjualan Mobile POS Nota: ' . $transaction->transaction_number,
                ]);
            }
        }
    }

    // =========================================================================
    // 4. AKSES APLIKASI & SUBSCRIPTION ROLE
    // =========================================================================
    public function getAppAccess(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant ?? $user->outlet->company ?? null;
        
        $isOwner = false;
        if (method_exists($user, 'isOwner')) {
            $isOwner = $user->isOwner();
        } elseif (method_exists($user, 'hasRole')) {
            $isOwner = $user->hasRole(['owner', 'admin', 'super-admin']);
        } elseif (isset($user->role_id) && clone $user->role_id == 1) {
            $isOwner = true;
        }

        $activeFeatures = [];
        if ($company && $company->subscriptionPlan) {
            $featuresData = $company->subscriptionPlan->features ?? [];
            if (is_string($featuresData)) {
                $featuresData = json_decode($featuresData, true);
            }

            if (is_array($featuresData)) {
                foreach ($featuresData as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            if ($subValue === true || $subValue === 1 || $subValue === '1') {
                                $activeFeatures[] = $subKey;
                            }
                        }
                    } else {
                        if ($value === true || $value === 1 || $value === '1') {
                            $activeFeatures[] = $key;
                        }
                    }
                }
            }
        }
        $activeFeatures = array_values(array_unique($activeFeatures));

        $userPermissions = [];
        if ($isOwner) {
            $userPermissions = ['*']; 
        } elseif (isset($user->role_id)) {
            try {
                $userPermissions = DB::table('role_permissions')
                    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_permissions.role_id', $user->role_id)
                    ->pluck('permissions.code')
                    ->toArray();
            } catch (\Exception $e) {
                $userPermissions = []; 
            }
        }

        return response()->json([
            'success' => true,
            'is_owner' => $isOwner,
            'active_features' => $activeFeatures,
            'user_permissions' => $userPermissions,
        ]);
    }

    // =========================================================================
    // 5. UPDATE PENGATURAN LOYALTY
    // =========================================================================
    public function updateLoyaltySettings(Request $request)
    {
        $user = $request->user();
        $company = $user->company ?? $user->tenant;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Tenant/Perusahaan tidak ditemukan.'], 404);
        }

        $isOwner = false;
        if (method_exists($user, 'isOwner')) {
            $isOwner = $user->isOwner();
        } elseif (isset($user->role_id) && clone $user->role_id == 1) {
            $isOwner = true;
        }

        if (!$isOwner) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengubah pengaturan ini.'], 403);
        }

        $validated = $request->validate([
            'is_loyalty_enabled' => 'required|boolean',
            'loyalty_spend_amount' => 'required|numeric|min:0',
            'loyalty_point_earned' => 'required|numeric|min:0',
            'loyalty_point_value' => 'required|numeric|min:0',
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan Poin & Loyalitas berhasil disimpan.'
        ]);
    }
}