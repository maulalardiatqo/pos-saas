<?php

namespace App\Filament\Tenant\Resources\Expenses\Pages;

use App\Filament\Tenant\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Account; // Pastikan import Model Account

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = filament()->getTenant()->id;
        $data['user_id'] = auth()->id();
        
        $data['transaction_number'] = 'EXP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        
        $data['subtotal'] = $data['grand_total'];
        $data['amount_paid'] = $data['grand_total'];

        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record;
        if ($transaction->account_id && $transaction->in_out === 'out') {
            Account::where('id', $transaction->account_id)
                ->decrement('balance', $transaction->grand_total);
        }
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}