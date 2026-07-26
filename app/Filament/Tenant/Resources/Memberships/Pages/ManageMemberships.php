<?php

namespace App\Filament\Tenant\Resources\Memberships\Pages;

use App\Filament\Tenant\Resources\Memberships\MembershipResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMemberships extends ManageRecords
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Level'),
        ];
    }
}