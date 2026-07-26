<?php

namespace App\Filament\Tenant\Resources\Memberships\Pages;

use App\Filament\Tenant\Resources\Memberships\MembershipResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;
}
