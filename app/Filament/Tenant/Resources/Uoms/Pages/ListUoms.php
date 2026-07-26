<?php

namespace App\Filament\Tenant\Resources\Uoms\Pages;

use App\Filament\Tenant\Resources\Uoms\UomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUoms extends ListRecords
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
