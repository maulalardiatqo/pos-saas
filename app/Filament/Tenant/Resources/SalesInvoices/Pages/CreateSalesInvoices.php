<?php

namespace App\Filament\Tenant\Resources\SalesInvoices\Pages;

use App\Filament\Tenant\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Stock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Account;
use App\Models\TransactionPayment;
use App\Models\PointHistory;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    // =========================================================================
    // PERBAIKAN 1: VALIDASI STOK (Menggunakan $this->data mentah)
    // =========================================================================
    protected function beforeCreate(): void
    {
        // KUNCI UTAMA: Kita memanggil $this->data langsung untuk menghindari 
        // pemfilteran otomatis dari Filament pada field Repeater Relationship.
        $data = $this->data; 
        
        $outletId = $data['outlet_id'] ?? auth()->user()->outlet_id;
        $requiredStocks = [];

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $productId = $item['product_id'] ?? null;
                if (!$productId) continue;

                $product = Product::find($productId);
                if (!$product || $product->item_type === 'service') continue;

                $isBundle = in_array($product->product_type, ['bundle', 'recipe']);
                
                $qty = (float) ($item['qty'] ?? 1);
                $factor = (float) ($item['conversion_factor'] ?? 1);
                
                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        if ($child && $child->item_type === 'goods') {
                            $qtyNeeded = $qty * $factor * (float)$comp->quantity;
                            // Akumulasi jika ada item yang diinput 2 baris (berulang)
                            $requiredStocks[$child->id] = ($requiredStocks[$child->id] ?? 0) + $qtyNeeded;
                        }
                    }
                } else {
                    $qtyNeeded = $qty * $factor;
                    $requiredStocks[$product->id] = ($requiredStocks[$product->id] ?? 0) + $qtyNeeded;
                }
            }
        }

        // Cek ke Database (Kunci Validasi)
        foreach ($requiredStocks as $productId => $totalNeeded) {
            $stockRecord = Stock::where('product_id', $productId)->where('outlet_id', $outletId)->first();
            $stockAvailable = $stockRecord ? (float) $stockRecord->qty : 0;
            
            if ($totalNeeded > $stockAvailable) {
                $prodName = Product::where('id', $productId)->value('name');
                
                // Munculkan notifikasi merah yang menempel (persistent)
                Notification::make()
                    ->title("Peringatan: Stok Tidak Cukup!")
                    ->body("Barang '{$prodName}' butuh {$totalNeeded}, sedangkan stok hanya tersedia {$stockAvailable}.")
                    ->danger()
                    ->persistent()
                    ->send();
                
                // Hentikan proses simpan seketika, kembalikan user ke form!
                $this->halt();
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $amountPaid = (float)($data['amount_paid'] ?? 0);
        $grandTotal = (float)($data['grand_total'] ?? 0);
        
        $data['amount_change'] = $amountPaid - $grandTotal; // Minus berarti hutang
        $data['status'] = $amountPaid >= $grandTotal ? 'completed' : 'pending';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record;

        // 1. CATAT PEMBAYARAN DP (UANG MUKA)
        if ($transaction->amount_paid > 0) {
            TransactionPayment::create([
                'company_id'     => $transaction->company_id,
                'outlet_id'      => $transaction->outlet_id,
                'transaction_id' => $transaction->id,
                'account_id'     => $transaction->account_id,
                'user_id'        => auth()->id(),
                'amount'         => $transaction->amount_paid,
                'payment_date'   => now(),
                'payment_method' => $transaction->payment_method ?? 'cash',
                'notes'          => 'Pembayaran Uang Muka (DP)',
                'payment_status' => 'success',
            ]);

            if ($transaction->account_id) {
                Account::where('id', $transaction->account_id)->increment('balance', $transaction->amount_paid);
            }
        }

        // =========================================================================
        // PERBAIKAN 2: LOGIKA LOYALTY POINTS (Sudah persis dengan POS)
        // =========================================================================
        $company = filament()->getTenant();

        if ($transaction->customer_id && $company->is_loyalty_enabled && $company->loyalty_spend_amount > 0) {
            $earnedMultiplier = floor($transaction->grand_total / $company->loyalty_spend_amount);
            $earnedPoints = $earnedMultiplier * (int) $company->loyalty_point_earned; 

            if ($earnedPoints > 0) {
                PointHistory::create([
                    'company_id'   => $company->id, 
                    'customer_id'  => $transaction->customer_id,
                    'type'         => 'earn', 
                    'amount'       => $earnedPoints,
                    'reference_id' => $transaction->transaction_number, 
                    'description'  => 'Poin dari Penjualan Invoice: ' . $transaction->transaction_number,
                ]);

                // Tambahkan poin ke profil pelanggan
                Customer::where('id', $transaction->customer_id)->increment('points_balance', $earnedPoints);
            }
        }

        // =========================================================================
        // 3. PEMOTONGAN STOK FISIK
        // =========================================================================
        foreach ($transaction->items as $item) {
            if (!$item->product_id) continue;
            
            $product = $item->product;
            if (!$product || $product->item_type === 'service') continue;

            $isBundle = in_array($product->product_type, ['bundle', 'recipe']);

            if ($isBundle) {
                $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                foreach ($components as $comp) {
                    $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                    if ($child && $child->item_type === 'goods') {
                        $qtyToDeduct = $item->base_qty * (float)$comp->quantity;
                        
                        $stockRecord = Stock::firstOrCreate(
                            ['company_id' => $transaction->company_id, 'outlet_id' => $transaction->outlet_id, 'product_id' => $comp->child_product_id],
                            ['qty' => 0]
                        );
                        $stockRecord->lockForUpdate();
                        
                        $balanceBefore = (float) $stockRecord->qty;
                        $balanceAfter = $balanceBefore - $qtyToDeduct;
                        $stockRecord->update(['qty' => $balanceAfter]);

                        StockMovement::create([
                            'company_id' => $transaction->company_id, 'outlet_id' => $transaction->outlet_id, 'product_id' => $comp->child_product_id,
                            'type' => 'sale', 'reference_type' => get_class($transaction), 'reference_id' => $transaction->id,
                            'quantity' => $qtyToDeduct, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                            'remarks' => "Terjual (Paket) Invoice: " . $transaction->transaction_number,
                        ]);
                    }
                }
            } else {
                $stockRecord = Stock::firstOrCreate(
                    ['company_id' => $transaction->company_id, 'outlet_id' => $transaction->outlet_id, 'product_id' => $product->id],
                    ['qty' => 0]
                );
                $stockRecord->lockForUpdate();
                
                $balanceBefore = (float) $stockRecord->qty;
                $balanceAfter = $balanceBefore - $item->base_qty;
                $stockRecord->update(['qty' => $balanceAfter]);

                StockMovement::create([
                    'company_id' => $transaction->company_id, 'outlet_id' => $transaction->outlet_id, 'product_id' => $product->id,
                    'type' => 'sale', 'reference_type' => get_class($transaction), 'reference_id' => $transaction->id,
                    'quantity' => $item->base_qty, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                    'remarks' => 'Penjualan Tempo (Invoice): ' . $transaction->transaction_number,
                ]);
            }
        }
    }
}