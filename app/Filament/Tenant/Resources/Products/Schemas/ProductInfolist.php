<?php

namespace App\Filament\Tenant\Resources\Products\Schemas;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

// Actions (Paten Sesuai Aturan)
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Foto')
                    ->square(),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('item_type')
                    ->label('Jenis Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'goods' => 'Barang',
                        'service' => 'Jasa',
                        default => $state,
                    }),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('baseUom.name')
                    ->label('Satuan Dasar')
                    ->sortable(),

                TextColumn::make('base_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('item_type')
                    ->label('Jenis Product')
                    ->options([
                        'goods'   => 'Barang',
                        'service' => 'Jasa'
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}