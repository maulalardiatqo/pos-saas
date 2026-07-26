<?php

namespace App\Filament\Tenant\Resources\Revenues\Pages;

use App\Filament\Tenant\Resources\Revenues\RevenueResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Account; // Wajib di-import

class CreateRevenue extends CreateRecord
{
    protected static string $resource = RevenueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = filament()->getTenant()->id;
        $data['user_id'] = auth()->id();
        
        $data['transaction_number'] = 'REV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        
        $data['subtotal'] = $data['grand_total'];
        $data['amount_paid'] = $data['grand_total'];

        return $data;
    }

    // FUNGSI OTOMATIS TAMBAH SALDO
    protected function afterCreate(): void
    {
        $transaction = $this->record;

        // Pastikan ada akun tujuan dan tipe in_out adalah 'in'
        if ($transaction->account_id && $transaction->in_out === 'in') {
            // Tambahkan (increment) saldo akun terkait sebesar grand_total
            Account::where('id', $transaction->account_id)
                ->increment('balance', $transaction->grand_total);
        }
    }
    
    // Opsional: Redirect kembali ke tabel setelah simpan
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}