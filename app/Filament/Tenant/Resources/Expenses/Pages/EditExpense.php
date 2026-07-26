<?php

namespace App\Filament\Tenant\Resources\Expenses\Pages;

use App\Filament\Tenant\Resources\Expenses\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Account;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    public $oldAmount = 0;
    public $oldAccountId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    if ($record->account_id) {
                        $account = Account::find($record->account_id);
                        if ($account) {
                            $account->increment('balance', $record->grand_total);
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

    protected function beforeSave(): void
    {
        $this->oldAmount = $this->record->grand_total;
        $this->oldAccountId = $this->record->account_id;

        if ($this->oldAccountId) {
            $oldAccount = Account::find($this->oldAccountId);
            if ($oldAccount) {
                $oldAccount->increment('balance', $this->oldAmount);
            }
        }
    }

    protected function afterSave(): void
    {
        $transaction = $this->record;

        if ($transaction->account_id) {
            $newAccount = Account::find($transaction->account_id);
            if ($newAccount) {
                $newAccount->decrement('balance', $transaction->grand_total);
            }
        }
    }
}