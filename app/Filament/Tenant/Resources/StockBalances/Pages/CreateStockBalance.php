<?php

namespace App\Filament\Tenant\Resources\StockBalances\Pages;

use App\Filament\Tenant\Resources\StockBalances\StockBalanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockBalance extends CreateRecord
{
    protected static string $resource = StockBalanceResource::class;
}
