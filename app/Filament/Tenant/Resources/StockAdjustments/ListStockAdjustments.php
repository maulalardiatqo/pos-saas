<?php

namespace App\Filament\Tenant\Resources\Inventory\StockAdjustments\Pages;

use App\Filament\Tenant\Resources\Inventory\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}