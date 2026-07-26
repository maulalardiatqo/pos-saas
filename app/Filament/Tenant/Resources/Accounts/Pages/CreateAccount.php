<?php

namespace App\Filament\Tenant\Resources\Accounts\Pages;

use App\Filament\Tenant\Resources\Accounts\AccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;
}
