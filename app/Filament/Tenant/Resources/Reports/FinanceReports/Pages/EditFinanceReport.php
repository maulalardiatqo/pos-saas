<?php

namespace App\Filament\Tenant\Resources\Reports\FinanceReports\Pages;

use App\Filament\Tenant\Resources\Reports\FinanceReports\FinanceReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFinanceReport extends EditRecord
{
    protected static string $resource = FinanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
