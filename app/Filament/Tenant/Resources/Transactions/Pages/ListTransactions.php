<?php

namespace App\Filament\Tenant\Resources\Transactions\Pages;

use App\Filament\Tenant\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->where('type', 'sale')->latest();
    }
}