<?php

namespace App\Observers;

use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockAdjustmentObserver
{
    // HAPUS function created() dan updated() di sini

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
            
            foreach ($stockAdjustment->items as $item) {
                
                // 1. Cari saldo terakhir
                $lastMovement = StockMovement::where('product_id', $item->product_id)
                    ->where('outlet_id', $stockAdjustment->outlet_id)
                    ->latest('created_at')
                    ->first();
                    
                $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0;
                
                $pivotData = DB::table('product_uoms')
                    ->where('product_id', $item->product_id)
                    ->where('uom_id', $item->uom_id) 
                    ->whereNull('deleted_at')
                    ->first();
                $conversionFactor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                
                $qtyToMutate = (float) $item->quantity * $conversionFactor; 
                
                if ($item->type === 'deduction') {
                    $balanceAfter = $balanceBefore - $qtyToMutate;
                } else { 
                    $balanceAfter = $balanceBefore + $qtyToMutate;
                }
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