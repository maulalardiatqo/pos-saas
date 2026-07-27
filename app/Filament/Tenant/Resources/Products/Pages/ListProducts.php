<?php

namespace App\Filament\Tenant\Resources\Products\Pages;

use App\Filament\Tenant\Resources\Products\ProductResource;
use App\Filament\Imports\ProductImporter;
use Filament\Actions\ImportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ProductImporter::class)
                ->color('success') 
                ->icon('heroicon-o-arrow-down-tray') 
                ->visible(fn () => static::getResource()::canCreate()), 
                
            CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}