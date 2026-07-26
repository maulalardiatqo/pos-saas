<?php

namespace App\Filament\Tenant\Resources\OpeningBalances\Pages;

use App\Filament\Tenant\Resources\OpeningBalances\OpeningBalanceResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Account; // <-- Jangan lupa import model Account

class CreateOpeningBalance extends CreateRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = filament()->getTenant()->id;
        $data['user_id'] = auth()->id();
        
        $nominal = (float) $data['amount_paid'];
        $data['subtotal'] = $nominal;
        $data['grand_total'] = $nominal;

        return $data;
    }
    protected function afterCreate(): void
    {
        $transaction = $this->record; 

        if ($transaction->account_id) {
            $account = Account::find($transaction->account_id);
            
            if ($account) {
                $account->increment('balance', $transaction->grand_total);
            }
        }
    }
}