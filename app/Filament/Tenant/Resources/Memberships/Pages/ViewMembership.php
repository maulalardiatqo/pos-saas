<?php

namespace App\Filament\Tenant\Resources\Memberships\Pages;

use App\Filament\Tenant\Resources\Memberships\MembershipResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMembership extends ViewRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
