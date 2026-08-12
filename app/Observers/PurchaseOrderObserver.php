<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Stock;
use App\Models\Account; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderObserver
{
    public function creating(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->type = 'purchaseorder';

        if (empty($purchaseOrder->company_id) && function_exists('filament') && filament()->getTenant()) {
            $purchaseOrder->company_id = filament()->getTenant()->id;
        }

        if (empty($purchaseOrder->payment_method)) {
            $purchaseOrder->payment_method = 'credit';
        }
        
        if (empty($purchaseOrder->notes)) {
            $purchaseOrder->notes = 'PO-' . date('Ymd-His') . '-' . rand(10, 99);
        }

        if (empty($purchaseOrder->transaction_number)) {
            $purchaseOrder->transaction_number = 'PO-' . date('Ymd-His') . '-' . rand(10, 99);
        }
    }

    public function processStockMovements(PurchaseOrder $purchaseOrder): void
    {
        /** @var \App\Models\Company $company */
        $company = filament()->getTenant();
        
        if (!$company) {
            return;
        }

        $hasGoodsReceive = (bool) $company->hasFeature('purchase.goods_receive');

        if ($hasGoodsReceive) {
            return; 
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->load('items.product');
            
            if ($purchaseOrder->items->isEmpty()) {
                return;
            }

            // -------------------------------------------------------------
            // 1. POTONG SALDO AKUN KEUANGAN (JIKA STATUS COMPLETED & ADA AKUN)
            // -------------------------------------------------------------
            $paymentAmount = (float) ($purchaseOrder->amount_paid > 0 ? $purchaseOrder->amount_paid : $purchaseOrder->grand_total);
            
            if (!empty($purchaseOrder->account_id) && $paymentAmount > 0) {
                Account::where('id', $purchaseOrder->account_id)
                    ->decrement('balance', $paymentAmount);
            }

            // Urutkan item untuk mencegah Deadlock pada tabel stocks dan products
            $items = $purchaseOrder->items->sortBy('product_id');

            foreach ($items as $item) {
                $itemType = $item->product->type ?? $item->product->item_type ?? '';
                if ($itemType === 'service') {
                    continue;
                }

                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();

                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                $qtyToMutate = (float) $item->qty * $conversionFactor; 

                // -------------------------------------------------------------
                // 2. AMBIL DAN KUNCI STOK (PESSIMISTIC LOCKING)
                // -------------------------------------------------------------
                $stockRecord = Stock::firstOrCreate(
                    [
                        'company_id' => $purchaseOrder->company_id,
                        'outlet_id'  => $purchaseOrder->outlet_id,
                        'product_id' => $item->product_id,
                    ],
                    ['qty' => 0]
                );

                // Kunci baris spesifik ini
                $stockRecord->lockForUpdate();

                $balanceBefore = (float) $stockRecord->qty;
                $balanceAfter = $balanceBefore + $qtyToMutate; 

                // UPDATE TABLE STOCKS
                $stockRecord->update(['qty' => $balanceAfter]);

                // 3. CATAT STOCK MOVEMENT
                StockMovement::create([
                    'company_id'     => $purchaseOrder->company_id,
                    'outlet_id'      => $purchaseOrder->outlet_id,
                    'product_id'     => $item->product_id,
                    'reference_type' => \App\Models\PurchaseOrder::class,
                    'reference_id'   => $purchaseOrder->id,
                    'type'           => 'purchase', 
                    'quantity'       => $qtyToMutate,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'remarks'        => 'Pembelian otomatis dari ' . $purchaseOrder->transaction_number,
                ]);

                // -------------------------------------------------------------
                // 4. LOGIKA MOVING AVERAGE (HPP BARU)
                // -------------------------------------------------------------
                $unitPrice = (float) ($item->cost_price > 0 ? $item->cost_price : $item->selling_price);
                $pricePerBaseUom = $conversionFactor > 0 ? ($unitPrice / $conversionFactor) : $unitPrice;
                
                $product = $item->product;
                $oldCost = (float) $product->cost_price;
                $totalStock = $balanceAfter; // Menggunakan stock mutlak setelah ditambahkan
                
                if ($totalStock > 0) {
                    $newCost = (($balanceBefore * $oldCost) + ($qtyToMutate * $pricePerBaseUom)) / $totalStock;
                    
                    $product->update([
                        'cost_price' => $newCost
                    ]);
                }

                // -------------------------------------------------------------
                // 5. UPDATE LAST PURCHASE PRICE (SUPPLIER)
                // -------------------------------------------------------------
                if (!empty($purchaseOrder->supplier_id)) {
                    $supplierPivot = DB::table('product_supplier')
                        ->where('product_id', $item->product_id)
                        ->where('supplier_id', $purchaseOrder->supplier_id)
                        ->first();

                    if ($supplierPivot) {
                        DB::table('product_supplier')
                            ->where('id', $supplierPivot->id)
                            ->update([
                                'last_purchase_price' => $pricePerBaseUom,
                                'updated_at'          => now(),
                            ]);
                    } else {
                        DB::table('product_supplier')->insert([
                            'id'                  => (string) Str::ulid(),
                            'product_id'          => $item->product_id,
                            'supplier_id'         => $purchaseOrder->supplier_id,
                            'last_purchase_price' => $pricePerBaseUom,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    }
                }
            }
        });
    }

    public function deleting(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status === 'completed') {
            
            $paymentAmount = (float) ($purchaseOrder->amount_paid > 0 ? $purchaseOrder->amount_paid : $purchaseOrder->grand_total);
            
            if (!empty($purchaseOrder->account_id) && $paymentAmount > 0) {
                Account::where('id', $purchaseOrder->account_id)
                    ->increment('balance', $paymentAmount);
            }
            $this->reverseStockMovements($purchaseOrder);
        }
    }

    protected function reverseStockMovements(PurchaseOrder $purchaseOrder): void
    {
        /** @var \App\Models\Company $company */
        $company = filament()->getTenant();
        
        if (!$company) {
            return;
        }

        $hasGoodsReceive = (bool) $company->hasFeature('purchase.goods_receive');
        if ($hasGoodsReceive) {
            return; 
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->load('items.product');
            
            if ($purchaseOrder->items->isEmpty()) {
                return;
            }

            // Urutkan item untuk mencegah Deadlock
            $items = $purchaseOrder->items->sortBy('product_id');

            foreach ($items as $item) {
                $itemType = $item->product->type ?? $item->product->item_type ?? '';
                if ($itemType === 'service') {
                    continue;
                }

                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();

                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                $qtyToMutate = (float) $item->qty * $conversionFactor; 

                // -------------------------------------------------------------
                // AMBIL DAN KUNCI STOK (PESSIMISTIC LOCKING) UNTUK REVERSE
                // -------------------------------------------------------------
                $stockRecord = Stock::firstOrCreate(
                    [
                        'company_id' => $purchaseOrder->company_id,
                        'outlet_id'  => $purchaseOrder->outlet_id,
                        'product_id' => $item->product_id,
                    ],
                    ['qty' => 0]
                );

                // Kunci baris spesifik ini
                $stockRecord->lockForUpdate();

                $balanceBefore = (float) $stockRecord->qty;
                
                // Karena di-reverse (dihapus/batal), stok DIKURANGI
                $balanceAfter = $balanceBefore - $qtyToMutate; 

                // UPDATE TABLE STOCKS
                $stockRecord->update(['qty' => $balanceAfter]);

                StockMovement::create([
                    'company_id'     => $purchaseOrder->company_id,
                    'outlet_id'      => $purchaseOrder->outlet_id,
                    'product_id'     => $item->product_id,
                    'reference_type' => \App\Models\PurchaseOrder::class,
                    'reference_id'   => $purchaseOrder->id,
                    'type'           => 'purchase_deleted', 
                    'quantity'       => $qtyToMutate,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'remarks'        => 'Pembatalan/Hapus PO Nota: ' . $purchaseOrder->transaction_number,
                ]);
            }
        });
    }
}