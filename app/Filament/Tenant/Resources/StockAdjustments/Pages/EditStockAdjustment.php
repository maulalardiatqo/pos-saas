<?php

namespace App\Filament\Tenant\Resources\StockAdjustments\Pages;

use App\Filament\Tenant\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Observers\StockAdjustmentObserver;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => static::getResource()::canDelete($this->record)),
        ];
    }
    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record->wasChanged('status') && $record->status === 'completed') {
            (new StockAdjustmentObserver())->processStockMovements($record);
        }
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}