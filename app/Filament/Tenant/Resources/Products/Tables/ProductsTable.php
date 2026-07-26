<?php

namespace App\Filament\Tenant\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Facades\Filament;
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('item_type')
                    ->label('Jenis Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    // PENGECEKAN FITUR: BARCODE
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    // PENGECEKAN FITUR: CATEGORY
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.category') === true),

                TextColumn::make('base_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->options([
                        'goods'            => 'Barang',
                        'service' => 'Jasa'
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
