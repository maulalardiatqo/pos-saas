<?php

namespace App\Filament\Tenant\Resources\Revenues\Pages;

use App\Filament\Tenant\Resources\Revenues\RevenueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRevenues extends ListRecords
{
    protected static string $resource = RevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
