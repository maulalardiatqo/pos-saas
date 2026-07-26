<?php

namespace App\Filament\Tenant\Resources\OpeningBalances\Pages;

// PERHATIKAN USE RESOURCE INI
use App\Filament\Tenant\Resources\OpeningBalances\OpeningBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpeningBalances extends ListRecords
{
    protected static string $resource = OpeningBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Input Saldo Awal'),
        ];
    }
}