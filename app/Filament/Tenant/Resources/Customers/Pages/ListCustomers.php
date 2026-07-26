<?php

namespace App\Filament\Tenant\Resources\Customers\Pages;

use App\Filament\Tenant\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Memaksa tombol untuk mematuhi logika penengah canCreate() di Resource
            CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}