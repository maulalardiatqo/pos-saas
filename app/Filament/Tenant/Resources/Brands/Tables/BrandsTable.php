<?php

namespace App\Filament\Tenant\Resources\Brands\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Brand')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Jml Produk')
                    ->badge(),
                
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                // Tambahkan filter jika diperlukan nanti
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