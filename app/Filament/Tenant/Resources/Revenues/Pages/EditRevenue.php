<?php

namespace App\Filament\Tenant\Resources\Revenues\Pages;

use App\Filament\Tenant\Resources\Revenues\RevenueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Account;

class EditRevenue extends EditRecord
{
    protected static string $resource = RevenueResource::class;

    public $oldAmount = 0;
    public $oldAccountId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    // JIKA DIHAPUS DARI FORM EDIT: Uang batal masuk -> tarik kembali dari rekening
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
        if (isset($data['grand_total'])) {
            $nominal = (float) $data['grand_total'];
            $data['subtotal'] = $nominal;
            $data['amount_paid'] = $nominal;
        }

        return $data;
    }

    // =========================================================================
    // HOOK 1: SEBELUM DISIMPAN (Tarik Uang Lama / Revert)
    // =========================================================================
    protected function beforeSave(): void
    {
        $this->oldAmount = $this->record->grand_total;
        $this->oldAccountId = $this->record->account_id;

        if ($this->oldAccountId) {
            $oldAccount = Account::find($this->oldAccountId);
            if ($oldAccount) {
                $oldAccount->decrement('balance', $this->oldAmount);
            }
        }
    }

    protected function afterSave(): void
    {
        $transaction = $this->record;

        // Tambahkan nominal yang baru ke rekening (increment)
        if ($transaction->account_id) {
            $newAccount = Account::find($transaction->account_id);
            if ($newAccount) {
                $newAccount->increment('balance', $transaction->grand_total);
            }
        }
    }
}