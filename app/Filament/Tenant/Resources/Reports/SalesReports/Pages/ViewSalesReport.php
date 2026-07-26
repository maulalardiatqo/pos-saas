<?php

namespace App\Filament\Tenant\Resources\Reports\SalesReports\Pages;

use App\Filament\Tenant\Resources\Reports\SalesReports\SalesReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReport extends ViewRecord
{
    protected static string $resource = SalesReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
