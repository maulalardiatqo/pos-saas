<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports\Pages;

use App\Filament\Tenant\Resources\Reports\ProductReports\ProductReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductReport extends CreateRecord
{
    protected static string $resource = ProductReportResource::class;
}
