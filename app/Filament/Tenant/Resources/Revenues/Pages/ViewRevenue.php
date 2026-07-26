<?php

namespace App\Filament\Tenant\Resources\Revenues\Pages;

use App\Filament\Tenant\Resources\Revenues\RevenueResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRevenue extends ViewRecord
{
    protected static string $resource = RevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
