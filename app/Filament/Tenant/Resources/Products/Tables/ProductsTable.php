<?php

namespace App\Filament\Tenant\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'goods'   => 'Barang/Fisik',
                        'service' => 'Jasa',
                        default   => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'goods'   => 'success',
                        'service' => 'warning',
                        default   => 'gray',
                    }),

                TextColumn::make('product_type')
                    ->label('Tipe Produk')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'standard' => 'Standar',
                        'bundle'   => 'Bundle (Paket)',
                        'recipe'   => 'Resep (BOM)',
                        default    => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard' => 'gray',
                        'bundle'   => 'info',
                        'recipe'   => 'primary',
                        default    => 'gray',
                    })
                    ->visible(fn () => 
                        data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.bundle') === true || 
                        data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.recipe') === true
                    ),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.category') === true),

                TextColumn::make('base_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->label('Jenis Produk') 
                    ->options([
                        'goods'   => 'Barang/Fisik',
                        'service' => 'Jasa'
                    ]),
                
                // TAMBAHAN: FILTER PRODUCT TYPE (TIPE PRODUK)
                SelectFilter::make('product_type')
                    ->label('Tipe Produk') 
                    ->options([
                        'standard' => 'Produk Standar (Biasa)',
                        'bundle'   => 'Paket Gabungan (Bundle)',
                        'recipe'   => 'Menu dengan Resep (BOM)'
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