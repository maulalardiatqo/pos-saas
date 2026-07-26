<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\StockMovement;
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

            foreach ($purchaseOrder->items as $item) {
                $itemType = $item->product->type ?? $item->product->item_type ?? '';
                if ($itemType === 'service') {
                    continue;
                }

                $lastMovement = StockMovement::where('product_id', $item->product_id)
                    ->where('outlet_id', $purchaseOrder->outlet_id)
                    ->latest('created_at')
                    ->first();
                    
                $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0;

                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();

                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                
                $qtyToMutate = (float) $item->qty * $conversionFactor; 
                $balanceAfter = $balanceBefore + $qtyToMutate; 

                // 2. CATAT STOCK MOVEMENT
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
                // 3. LOGIKA MOVING AVERAGE (HPP BARU)
                // -------------------------------------------------------------
                $unitPrice = (float) ($item->cost_price > 0 ? $item->cost_price : $item->selling_price);
                $pricePerBaseUom = $conversionFactor > 0 ? ($unitPrice / $conversionFactor) : $unitPrice;
                
                $product = $item->product;
                $oldCost = (float) $product->cost_price;
                $totalStock = $balanceBefore + $qtyToMutate;
                if ($totalStock > 0) {
                    $newCost = (($balanceBefore * $oldCost) + ($qtyToMutate * $pricePerBaseUom)) / $totalStock;
                    
                    $product->update([
                        'cost_price' => $newCost
                    ]);
                }

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

    // =================================================================
    // TAMBAHAN BARU: FUNGSI REVERSAL SAAT PO DIHAPUS
    // =================================================================
    public function deleting(PurchaseOrder $purchaseOrder): void
    {
        // 1. KEMBALIKAN SALDO KAS/REKENING (Jika waktu buat PO sudah potong saldo)
        if ($purchaseOrder->account_id && $purchaseOrder->in_out === 'out' && $purchaseOrder->amount_paid > 0) {
            Account::where('id', $purchaseOrder->account_id)
                ->increment('balance', $purchaseOrder->amount_paid);
        }

        // 2. KEMBALIKAN STOK BARANG (Hanya jika PO tersebut sebelumnya berstatus 'completed')
        if ($purchaseOrder->status === 'completed') {
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

            foreach ($purchaseOrder->items as $item) {
                $itemType = $item->product->type ?? $item->product->item_type ?? '';
                if ($itemType === 'service') {
                    continue;
                }

                $lastMovement = StockMovement::where('product_id', $item->product_id)
                    ->where('outlet_id', $purchaseOrder->outlet_id)
                    ->latest('created_at')
                    ->first();
                    
                $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0;

                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();

                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                
                $qtyToMutate = (float) $item->qty * $conversionFactor; 
                
                $balanceAfter = $balanceBefore - $qtyToMutate; 

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