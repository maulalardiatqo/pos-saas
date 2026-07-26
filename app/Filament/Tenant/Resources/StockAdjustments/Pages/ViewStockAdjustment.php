<?php

namespace App\Filament\Tenant\Resources\StockAdjustments\Pages;

use App\Filament\Tenant\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
