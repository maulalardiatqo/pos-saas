<?php

namespace App\Filament\Tenant\Resources\StockAdjustments\Pages;

use App\Filament\Tenant\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Observers\StockAdjustmentObserver;

class CreateStockAdjustment extends CreateRecord
{
    protected static string $resource = StockAdjustmentResource::class;
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
    
        if ($record->status === 'completed') {
            (new StockAdjustmentObserver())->processStockMovements($record);
        }
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}