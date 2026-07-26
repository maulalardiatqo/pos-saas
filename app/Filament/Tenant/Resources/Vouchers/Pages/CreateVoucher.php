<?php

namespace App\Filament\Tenant\Resources\Vouchers\Pages;

use App\Filament\Tenant\Resources\Vouchers\VoucherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;
}
