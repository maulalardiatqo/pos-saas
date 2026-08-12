<?php

namespace App\Filament\Tenant\Resources\Suppliers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter; // <-- Tambahan
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Nama Pemasok')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // KOLOM OUTLET (Hanya terlihat jika Owner/Platform)
                TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->badge()
                    ->color('info')
                    ->default('Global / Semua Cabang')
                    ->visible(fn () => auth()->user()->isOwner() || auth()->user()->isPlatform()),

                TextColumn::make('contact_person')
                    ->label('PIC')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('outlet_id')
                    ->label('Filter Outlet')
                    ->relationship('outlet', 'name')
                    ->visible(fn () => auth()->user()->isOwner() || auth()->user()->isPlatform()),
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