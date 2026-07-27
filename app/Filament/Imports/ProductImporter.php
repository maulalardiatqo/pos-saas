<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nama Produk')
                ->requiredMapping()
                ->rules(['required', 'max:200']),
                
            ImportColumn::make('sku')
                ->label('SKU')
                ->rules(['max:100']),
                
            ImportColumn::make('barcode')
                ->label('Barcode')
                ->rules(['max:100']),

            ImportColumn::make('base_uom_id')
                ->label('ID Satuan (Base UOM ID)')
                ->requiredMapping()
                ->rules(['required', 'string']),
                
            ImportColumn::make('cost_price')
                ->label('Harga Modal')
                ->numeric()
                ->rules(['numeric']), // <-- Hapus ->default(0) di sini
                
            ImportColumn::make('base_price')
                ->label('Harga Jual')
                ->numeric()
                ->rules(['numeric']), // <-- Hapus ->default(0) di sini
                
            ImportColumn::make('item_type')
                ->label('Tipe Item (goods/service)')
                ->rules(['in:goods,service']),
                
            ImportColumn::make('product_type')
                ->label('Tipe Produk (standard/bundle/recipe)')
                ->rules(['in:standard,bundle,recipe']),
                
            ImportColumn::make('description')
                ->label('Deskripsi'),
        ];
    }

    public function resolveRecord(): ?Product
    {

        $product = new Product();
        if ($tenant = filament()->getTenant()) {
            $product->company_id = $tenant->id;
        }

        return $product;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import produk telah selesai. ' . number_format($import->successful_rows) . ' baris berhasil diimpor.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount) . ' baris yang gagal diimpor.';
        }

        return $body;
    }
}