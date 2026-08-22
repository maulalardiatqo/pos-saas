<?php

namespace App\Filament\Tenant\Resources\Assets\Pages;

use App\Filament\Tenant\Resources\Assets\AssetResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Transaction;
use App\Models\AssetLog;
use App\Models\Account;
use Filament\Notifications\Notification;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function beforeCreate(): void
    {
        // 1. Ambil input dari form yang tidak masuk ke database (dehydrated: false)
        $acquisitionType = $this->form->getRawState()['acquisition_type'] ?? 'opening';
        $accountId       = $this->form->getRawState()['account_id'] ?? null;
        $purchasePrice   = (float) ($this->data['purchase_price'] ?? 0);

        // ==============================================================
        // PERBAIKAN: CEK SALDO KAS/BANK SEBELUM MEMBELI ASET
        // ==============================================================
        if ($acquisitionType === 'purchase' && $accountId) {
            $account = Account::find($accountId);
            
            if (!$account) {
                Notification::make()->title('Rekening tidak ditemukan!')->danger()->send();
                $this->halt(); // Batalkan proses simpan
            }

            // Jika saldo kurang dari harga beli aset, tolak dan beri peringatan
            if ($account->balance < $purchasePrice) {
                Notification::make()
                    ->title('Pembelian Dibatalkan!')
                    ->body("Saldo di rekening {$account->name} tidak mencukupi. (Sisa Saldo: Rp " . number_format($account->balance, 0, ',', '.') . ")")
                    ->danger()
                    ->persistent()
                    ->send();
                    
                $this->halt(); // Batalkan proses simpan
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan asset terecord ke dalam tenant saat ini
        $data['company_id'] = filament()->getTenant()->id;

        $acquisitionType = $this->form->getRawState()['acquisition_type'] ?? 'opening';
        $paymentMethod   = $this->form->getRawState()['payment_method'] ?? 'cash';
        $accountId       = $this->form->getRawState()['account_id'] ?? null;

        // Jika Pembelian Baru, catat sebagai Uang Keluar (Transaction Out)
        if ($acquisitionType === 'purchase' && $accountId) {
            $transaction = Transaction::create([
                'company_id'         => filament()->getTenant()->id,
                'outlet_id'          => $data['outlet_id'], 
                'user_id'            => auth()->id(),
                'account_id'         => $accountId, 
                'in_out'             => 'out',      // Menandakan Arus Uang Keluar
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
        // 1. LOG PENCATATAN AWAL ASET
        AssetLog::create([
            'company_id'   => $this->record->company_id,
            'asset_id'     => $this->record->id,
            'user_id'      => auth()->id(),
            'action_type'  => 'created',
            'to_outlet_id' => $this->record->outlet_id,
            'remarks'      => 'Pendataan awal aset di sistem.',
        ]);

        // ==============================================================
        // 2. PEMOTONGAN SALDO KAS/BANK SECARA FISIK DI DATABASE
        // ==============================================================
        $acquisitionType = $this->form->getRawState()['acquisition_type'] ?? 'opening';
        $accountId       = $this->form->getRawState()['account_id'] ?? null;

        if ($acquisitionType === 'purchase' && $accountId) {
            Account::where('id', $accountId)
                ->decrement('balance', $this->record->purchase_price);
        }
    }
}