<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\StockMovement;
use App\Models\Stock; // <-- PENTING: Model Stock baru kita
use Illuminate\Support\Facades\DB;

class StockTransferObserver
{
    public function processStockMovements(StockTransfer $stockTransfer): void
    {
        DB::transaction(function () use ($stockTransfer) {
            $stockTransfer->load('items');

            if ($stockTransfer->items->isEmpty()) {
                return;
            }

            $items = $stockTransfer->items->sortBy('product_id');

            foreach ($items as $item) {
                $qtyToMutate = (float) $item->quantity;

                $sourceStock = Stock::firstOrCreate(
                    [
                        'company_id' => $stockTransfer->company_id,
                        'outlet_id'  => $stockTransfer->from_outlet_id,
                        'product_id' => $item->product_id,
                    ],
                    ['qty' => 0]
                );

                $sourceStock->lockForUpdate();

                $sourceBalanceBefore = (float) $sourceStock->qty;
                $sourceBalanceAfter  = $sourceBalanceBefore - $qtyToMutate;

                $sourceStock->update(['qty' => $sourceBalanceAfter]);

                StockMovement::create([
                    'company_id'     => $stockTransfer->company_id,
                    'outlet_id'      => $stockTransfer->from_outlet_id,
                    'product_id'     => $item->product_id,
                    'type'           => 'transfer_out',
                    'reference_type' => \App\Models\StockTransfer::class,
                    'reference_id'   => $stockTransfer->id,
                    'quantity'       => -$qtyToMutate, 
                    'balance_before' => $sourceBalanceBefore,
                    'balance_after'  => $sourceBalanceAfter,
                    'remarks'        => 'Transfer keluar ke tujuan. Ref: ' . $stockTransfer->reference_number,
                ]);
                $destStock = Stock::firstOrCreate(
                    [
                        'company_id' => $stockTransfer->company_id,
                        'outlet_id'  => $stockTransfer->to_outlet_id,
                        'product_id' => $item->product_id,
                    ],
                    ['qty' => 0]
                );

                $destStock->lockForUpdate();

                $destBalanceBefore = (float) $destStock->qty;
                $destBalanceAfter  = $destBalanceBefore + $qtyToMutate;

                $destStock->update(['qty' => $destBalanceAfter]);

                StockMovement::create([
                    'company_id'     => $stockTransfer->company_id,
                    'outlet_id'      => $stockTransfer->to_outlet_id,
                    'product_id'     => $item->product_id,
                    'type'           => 'transfer_in',
                    'reference_type' => \App\Models\StockTransfer::class,
                    'reference_id'   => $stockTransfer->id,
                    'quantity'       => $qtyToMutate, 
                    'balance_before' => $destBalanceBefore,
                    'balance_after'  => $destBalanceAfter,
                    'remarks'        => 'Transfer masuk dari asal. Ref: ' . $stockTransfer->reference_number,
                ]);
            }
        });
    }

    /**
     * Opsional: Jika Anda memperbolehkan pembatalan/penghapusan Transfer
     */
    public function reverseStockMovements(StockTransfer $stockTransfer): void
    {
        DB::transaction(function () use ($stockTransfer) {
            $stockTransfer->load('items');

            if ($stockTransfer->items->isEmpty()) {
                return;
            }

            $items = $stockTransfer->items->sortBy('product_id');

            foreach ($items as $item) {
                $qtyToMutate = (float) $item->quantity;

                // 1. KEMBALIKAN KE GUDANG ASAL (Ditambah)
                $sourceStock = Stock::where('outlet_id', $stockTransfer->from_outlet_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($sourceStock) {
                    $sourceStock->lockForUpdate();
                    $sourceBalanceBefore = (float) $sourceStock->qty;
                    $sourceBalanceAfter  = $sourceBalanceBefore + $qtyToMutate;
                    $sourceStock->update(['qty' => $sourceBalanceAfter]);

                    StockMovement::create([
                        'company_id'     => $stockTransfer->company_id,
                        'outlet_id'      => $stockTransfer->from_outlet_id,
                        'product_id'     => $item->product_id,
                        'type'           => 'transfer_deleted',
                        'reference_type' => \App\Models\StockTransfer::class,
                        'reference_id'   => $stockTransfer->id,
                        'quantity'       => $qtyToMutate,
                        'balance_before' => $sourceBalanceBefore,
                        'balance_after'  => $sourceBalanceAfter,
                        'remarks'        => 'Batal Transfer: Dikembalikan dari tujuan. Ref: ' . $stockTransfer->reference_number,
                    ]);
                }

                // 2. TARIK DARI GUDANG TUJUAN (Dikurangi)
                $destStock = Stock::where('outlet_id', $stockTransfer->to_outlet_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($destStock) {
                    $destStock->lockForUpdate();
                    $destBalanceBefore = (float) $destStock->qty;
                    $destBalanceAfter  = $destBalanceBefore - $qtyToMutate;
                    $destStock->update(['qty' => $destBalanceAfter]);

                    StockMovement::create([
                        'company_id'     => $stockTransfer->company_id,
                        'outlet_id'      => $stockTransfer->to_outlet_id,
                        'product_id'     => $item->product_id,
                        'type'           => 'transfer_deleted',
                        'reference_type' => \App\Models\StockTransfer::class,
                        'reference_id'   => $stockTransfer->id,
                        'quantity'       => -$qtyToMutate,
                        'balance_before' => $destBalanceBefore,
                        'balance_after'  => $destBalanceAfter,
                        'remarks'        => 'Batal Transfer: Ditarik kembali ke asal. Ref: ' . $stockTransfer->reference_number,
                    ]);
                }
            }
        });
    }
}