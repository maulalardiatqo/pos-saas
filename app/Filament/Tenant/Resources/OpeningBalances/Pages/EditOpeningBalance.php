<?php

namespace App\Filament\Tenant\Resources\OpeningBalances\Pages;

use App\Filament\Tenant\Resources\OpeningBalances\OpeningBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Account; // <-- Wajib import model Account

class EditOpeningBalance extends EditRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    // Siapkan wadah (property) untuk menyimpan data lama sementara
    public $oldAmount = 0;
    public $oldAccountId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    // JIKA DIHAPUS: Tarik kembali (kurangi) saldo dari rekening
                    if ($record->account_id) {
                        $account = Account::find($record->account_id);
                        if ($account) {
                            $account->decrement('balance', $record->grand_total);
                        }
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['amount_paid'])) {
            $nominal = (float) $data['amount_paid'];
            $data['subtotal'] = $nominal;
            $data['grand_total'] = $nominal;
        }

        return $data;
    }

    // =========================================================================
    // HOOK 1: SEBELUM DISIMPAN (Ambil dan Tarik Saldo Lama)
    // =========================================================================
    protected function beforeSave(): void
    {
        // 1. Simpan data lama ke dalam property class
        $this->oldAmount = $this->record->grand_total;
        $this->oldAccountId = $this->record->account_id;

        // 2. Tarik (kurangi) saldo akun yang lama
        if ($this->oldAccountId) {
            $oldAccount = Account::find($this->oldAccountId);
            if ($oldAccount) {
                $oldAccount->decrement('balance', $this->oldAmount);
            }
        }
    }

    // =========================================================================
    // HOOK 2: SETELAH DISIMPAN (Setor Saldo Baru)
    // =========================================================================
    protected function afterSave(): void
    {
        $transaction = $this->record;

        if ($transaction->account_id) {
            $newAccount = Account::find($transaction->account_id);
            if ($newAccount) {
                $newAccount->increment('balance', $transaction->grand_total);
            }
        }
    }
}