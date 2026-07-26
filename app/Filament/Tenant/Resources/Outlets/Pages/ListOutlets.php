<?php

namespace App\Filament\Tenant\Resources\Outlets\Pages;

use App\Filament\Tenant\Resources\Outlets\OutletResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutlets extends ListRecords
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
