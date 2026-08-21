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

    protected function beforeCreate(): void
    {
        $data = $this->data; 
        
        $outletId = $data['outlet_id'] ?? auth()->user()->outlet_id;
        $requiredStocks = [];

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $productId = $item['product_id'] ?? null;
                if (!$productId) continue;

                $product = Product::find($productId);
                if (!$product || $product->item_type === 'service') continue;

                $factor = 1;
                if (!empty($item['uom_id'])) {
                    $uomPivot = DB::table('product_uoms')->where('product_id', $productId)->where('uom_id', $item['uom_id'])->first();
                    if ($uomPivot) $factor = (float) $uomPivot->conversion_factor;
                }
                
                $qty = (float) ($item['qty'] ?? 1);
                $isBundle = in_array($product->product_type, ['bundle', 'recipe']);
                
                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        if ($child && $child->item_type === 'goods') {
                            $compFactor = 1;
                            if (!empty($comp->uom_id)) {
                                $childUom = DB::table('product_uoms')->where('product_id', $child->id)->where('uom_id', $comp->uom_id)->first();
                                if ($childUom) $compFactor = (float) $childUom->conversion_factor;
                            }
                            $qtyNeeded = $qty * $factor * ((float)$comp->quantity * $compFactor);
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
                
                Notification::make()
                    ->title("Peringatan: Stok Tidak Cukup!")
                    ->body("Barang '{$prodName}' butuh {$totalNeeded}, sedangkan stok hanya tersedia {$stockAvailable}.")
                    ->danger()
                    ->persistent()
                    ->send();
                
                $this->halt();
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ==============================================================
        // PENGECEKAN POIN UNTUK DATABASE
        // ==============================================================
        $pointsToRedeem = (int) ($data['points_to_redeem'] ?? 0);
        $pointValue = (float) (filament()->getTenant()->loyalty_point_value ?? 1);
        
        $data['points_used'] = $pointsToRedeem;
        $data['point_discount_amount'] = $pointsToRedeem * $pointValue;
        
        $amountPaid = (float)($data['amount_paid'] ?? 0);
        $grandTotal = (float)($data['grand_total'] ?? 0);
        
        $data['amount_change'] = $amountPaid - $grandTotal;
        $data['status'] = $amountPaid >= $grandTotal ? 'completed' : 'pending';
        
        // Buang field palsu agar tidak error saat INSERT
        unset($data['points_to_redeem']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record;
        $company = filament()->getTenant();

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

        // ==============================================================
        // LOGIKA PENUKARAN DAN PENDAPATAN POIN (SAMA DENGAN POS KASIR)
        // ==============================================================
        if ($transaction->points_used > 0 && $transaction->customer_id) {
            PointHistory::create([
                'company_id'   => $company->id, 
                'customer_id'  => $transaction->customer_id,
                'type'         => 'redeem', 
                'amount'       => $transaction->points_used,
                'reference_id' => $transaction->transaction_number, 
                'description'  => 'Tukar poin dari Invoice: ' . $transaction->transaction_number,
            ]);
            Customer::where('id', $transaction->customer_id)->decrement('points_balance', $transaction->points_used);
        }

        $hasCrm = data_get($company?->subscriptionPlan?->features, 'crm.membership') === true;

        if ($transaction->customer_id && $hasCrm && $company->loyalty_spend_amount > 0) {
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

                Customer::where('id', $transaction->customer_id)->increment('points_balance', $earnedPoints);
            }
        }

        // PEMOTONGAN STOK FISIK
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
                        $compFactor = 1;
                        if (!empty($comp->uom_id)) {
                            $uomPivot = DB::table('product_uoms')->where('product_id', $comp->child_product_id)->where('uom_id', $comp->uom_id)->first();
                            if ($uomPivot) $compFactor = (float) $uomPivot->conversion_factor;
                        }

                        $qtyToDeduct = $item->base_qty * ((float)$comp->quantity * $compFactor);
                        
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