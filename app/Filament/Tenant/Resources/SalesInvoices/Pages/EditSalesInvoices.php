<?php

namespace App\Filament\Tenant\Resources\SalesInvoices\Pages;

use App\Filament\Tenant\Resources\SalesInvoices\SalesInvoicesResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoices extends EditRecord
{
    protected static string $resource = SalesInvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
