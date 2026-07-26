<?php

namespace App\Filament\Tenant\Resources\Assets\Pages;

use App\Filament\Tenant\Resources\Assets\AssetResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Transaction;
use App\Models\AssetLog;
use App\Models\Account; // Wajib di-import untuk potong saldo

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ambil data virtual yang statusnya dehydrated(false)
        $acquisitionType = $this->form->getRawState()['acquisition_type'] ?? 'opening';
        $paymentMethod   = $this->form->getRawState()['payment_method'] ?? 'cash';
        $accountId       = $this->form->getRawState()['account_id'] ?? null;

        if ($acquisitionType === 'purchase') {
            $transaction = Transaction::create([
                'company_id'         => filament()->getTenant()->id,
                'outlet_id'          => $data['outlet_id'], 
                'user_id'            => auth()->id(),
                'account_id'         => $accountId, // Sambungkan ke akun yang dipilih
                'in_out'             => 'out',      // Menandakan Uang Keluar
                'transaction_number' => 'AST-BUY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'type'               => 'asset_purchase', 
                'status'             => 'completed',
                'payment_method'     => $paymentMethod,
                'subtotal'           => $data['purchase_price'],
                'grand_total'        => $data['purchase_price'],
                'amount_paid'        => $data['purchase_price'],
            ]);

            // Tautkan ID transaksi ini ke aset yang akan dibuat
            $data['transaction_id'] = $transaction->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // 1. LOG PENCATATAN ASET
        AssetLog::create([
            'company_id'   => $this->record->company_id,
            'asset_id'     => $this->record->id,
            'user_id'      => auth()->id(),
            'action_type'  => 'created',
            'to_outlet_id' => $this->record->outlet_id,
            'remarks'      => 'Pendataan awal aset di sistem.',
        ]);

        // 2. POTONG SALDO KAS/BANK (Jika perolehannya Pembelian)
        $acquisitionType = $this->form->getRawState()['acquisition_type'] ?? 'opening';
        $accountId       = $this->form->getRawState()['account_id'] ?? null;

        if ($acquisitionType === 'purchase' && $accountId) {
            Account::where('id', $accountId)
                ->decrement('balance', $this->record->purchase_price);
        }
    }
}