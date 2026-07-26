<?php

namespace App\Filament\Tenant\Resources\OpeningBalances\Pages;

use App\Filament\Tenant\Resources\OpeningBalances\OpeningBalanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOpeningBalance extends ViewRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
