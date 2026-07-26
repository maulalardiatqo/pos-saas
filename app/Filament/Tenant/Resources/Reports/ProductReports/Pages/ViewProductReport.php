<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports\Pages;

use App\Filament\Tenant\Resources\Reports\ProductReports\ProductReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductReport extends ViewRecord
{
    protected static string $resource = ProductReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
