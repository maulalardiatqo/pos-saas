<?php

namespace App\Observers;

use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Stock; 
use Illuminate\Support\Facades\DB;

class StockAdjustmentObserver
{
    /**
     * Ubah menjadi PUBLIC agar bisa dipanggil dari Filament Pages
     */
    public function processStockMovements(StockAdjustment $stockAdjustment): void
    {
        DB::transaction(function () use ($stockAdjustment) {
            $stockAdjustment->load('items');
            
            if ($stockAdjustment->items->isEmpty()) {
                return;
            }
            $items = $stockAdjustment->items->sortBy('product_id');

            foreach ($items as $item) {
                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();
                    
                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                $qtyToMutate = (float) $item->quantity * $conversionFactor; 
                $stockRecord = Stock::firstOrCreate(
                    [
                        'company_id' => $stockAdjustment->company_id,
                        'outlet_id'  => $stockAdjustment->outlet_id,
                        'product_id' => $item->product_id,
                    ],
                    ['qty' => 0] 
                );
                $stockRecord->lockForUpdate();

                $balanceBefore = (float) $stockRecord->qty;
                if ($item->type === 'deduction') {
                    $balanceAfter = $balanceBefore - $qtyToMutate;
                } else { 
                    $balanceAfter = $balanceBefore + $qtyToMutate;
                }

                $stockRecord->update(['qty' => $balanceAfter]);

                $stockAdjustment->movements()->create([
                    'company_id'     => $stockAdjustment->company_id,
                    'outlet_id'      => $stockAdjustment->outlet_id,
                    'product_id'     => $item->product_id,
                    'type'           => 'adjustment',
                    'quantity'       => $item->type === 'deduction' ? -$qtyToMutate : $qtyToMutate,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'remarks'        => $item->remarks ?? 'Penyesuaian manual: ' . $stockAdjustment->reason,
                ]);
            }
        });
    }
}