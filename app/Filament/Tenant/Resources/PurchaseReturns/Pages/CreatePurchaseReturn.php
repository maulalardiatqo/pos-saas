<?php

namespace App\Filament\Tenant\Resources\PurchaseReturns\Pages;

use App\Filament\Tenant\Resources\PurchaseReturns\PurchaseReturnResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Stock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->data;

        // 1. Filter Item: Hanya ambil barang yang diisi Qty-nya > 0
        $filteredItems = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                if ((float) ($item['qty'] ?? 0) > 0) {
                    $filteredItems[$key] = $item;
                }
            }
        }

        // 2. Validasi jika setelah difilter ternyata kosong semua
        if (empty($filteredItems)) {
            Notification::make()
                ->title('Gagal!')
                ->body('Anda belum mengisi jumlah barang yang mau diretur (Semua Qty masih 0).')
                ->danger()
                ->send();
                
            $this->halt(); // Hentikan proses simpan
        }

        $this->data['items'] = $filteredItems;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // =====================================================================
        // PERBAIKAN: Suntikkan User ID dan Outlet ID secara otomatis
        // =====================================================================
        $data['payment_method'] = 'refund'; 
        $data['user_id'] = auth()->id();
        
        // Kita tarik data Outlet ID dari Nota PO Asli agar stok gudangnya presisi
        if (!empty($data['reference_id'])) {
            $po = Transaction::find($data['reference_id']);
            $data['outlet_id'] = $po ? $po->outlet_id : auth()->user()->outlet_id;
        } else {
            $data['outlet_id'] = auth()->user()->outlet_id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record;

        // 1. TAMBAH UANG KE REKENING (Karena direfund oleh Supplier)
        if ($transaction->amount_paid > 0 && $transaction->account_id) {
            Account::where('id', $transaction->account_id)->increment('balance', $transaction->amount_paid);
        }

        // 2. PEMOTONGAN STOK FISIK (Barang ditarik/dikembalikan ke Supplier)
        // Gunakan outlet_id dari transaksi retur yang sudah diset di atas
        $outletId = $transaction->outlet_id;

        foreach ($transaction->items as $item) {
            if (!$item->product_id) continue;
            
            $product = $item->product;
            if (!$product || $product->item_type === 'service') continue;

            // Hitung base_qty berdasarkan UoM yang dipilih saat retur
            $factor = (float) $item->conversion_factor;
            $baseQtyToDeduct = $item->qty * $factor;
            
            // Simpan base_qty ke database agar pelaporan valid
            $item->update([
                'base_qty' => $baseQtyToDeduct,
                'selling_price' => 0 // Di refund pembelian tidak ada selling price
            ]);

            // Kunci baris stok agar tidak ada race condition
            $stockRecord = Stock::firstOrCreate(
                ['company_id' => $transaction->company_id, 'outlet_id' => $outletId, 'product_id' => $product->id],
                ['qty' => 0]
            );
            $stockRecord->lockForUpdate();
            
            $balanceBefore = (float) $stockRecord->qty;
            $balanceAfter = $balanceBefore - $baseQtyToDeduct; 
            $stockRecord->update(['qty' => $balanceAfter]);

            StockMovement::create([
                'company_id'     => $transaction->company_id, 
                'outlet_id'      => $outletId, 
                'product_id'     => $product->id,
                'type'           => 'purchase_return', 
                'reference_type' => get_class($transaction), 
                'reference_id'   => $transaction->id,
                'quantity'       => $baseQtyToDeduct, 
                'balance_before' => $balanceBefore, 
                'balance_after'  => $balanceAfter,
                'remarks'        => 'Retur Pembelian (Refund) Nota: ' . $transaction->transaction_number,
            ]);
        }
    }
}