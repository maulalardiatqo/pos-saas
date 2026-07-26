<?php

namespace App\Filament\Tenant\Resources\Reports\FinanceReports\Pages;

use App\Filament\Tenant\Resources\Reports\FinanceReports\FinanceReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinanceReport extends CreateRecord
{
    protected static string $resource = FinanceReportResource::class;
}
