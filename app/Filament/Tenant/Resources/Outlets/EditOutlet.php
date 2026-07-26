<?php

namespace App\Filament\Tenant\Resources\Outlets\Pages;

use App\Filament\Tenant\Resources\Outlets\OutletResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOutlet extends EditRecord
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}