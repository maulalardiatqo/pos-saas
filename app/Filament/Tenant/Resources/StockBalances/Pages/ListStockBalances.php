<?php

namespace App\Filament\Tenant\Resources\StockBalances\Pages;

use App\Filament\Tenant\Resources\StockBalances\StockBalanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockBalances extends ListRecords
{
    protected static string $resource = StockBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
