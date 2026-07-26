<?php

namespace App\Filament\Tenant\Resources\Vouchers\Pages;

use App\Filament\Tenant\Resources\Vouchers\VoucherResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
