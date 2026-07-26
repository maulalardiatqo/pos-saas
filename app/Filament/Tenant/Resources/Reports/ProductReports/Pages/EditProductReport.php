<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports\Pages;

use App\Filament\Tenant\Resources\Reports\ProductReports\ProductReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductReport extends EditRecord
{
    protected static string $resource = ProductReportResource::class;

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
