<?php

namespace App\Filament\Tenant\Resources\PurchaseOrders\Pages;

use App\Filament\Tenant\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;
use App\Observers\PurchaseOrderObserver;
use App\Models\Account; // Wajib di-import agar bisa memotong saldo

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record->status === 'completed') {
            (new PurchaseOrderObserver())->processStockMovements($record);
        }

        if ($record->account_id && $record->in_out === 'out' && $record->amount_paid > 0) {
            Account::where('id', $record->account_id)
                ->decrement('balance', $record->amount_paid);
        }
    }
}