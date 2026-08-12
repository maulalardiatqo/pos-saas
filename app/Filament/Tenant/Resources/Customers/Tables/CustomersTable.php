<?php

namespace App\Filament\Tenant\Resources\Customers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter; 
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;

class CustomersTable
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
                    ->label('Nama Pelanggan')
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

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-'),

                // DIHUBUNGKAN KE RELASI TABEL MEMBERSHIP
                TextColumn::make('membership.name')
                    ->label('Level Membership')
                    ->badge()
                    ->color('warning') 
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable()
                    ->visible(function () {
                        $features = Filament::getTenant()?->subscriptionPlan?->features;
                        return data_get($features, 'crm.membership') === true;
                    }),

                // MENGGUNAKAN KOLOM DATA TERBARU: points_balance
                TextColumn::make('points_balance')
                    ->label('Saldo Poin')
                    ->numeric()
                    ->sortable()
                    ->placeholder('0')
                    ->toggleable()
                    ->visible(function () {
                        $features = Filament::getTenant()?->subscriptionPlan?->features;
                        return data_get($features, 'crm.membership') === true;
                    }),
            ])
            ->filters([
                // FILTER OUTLET (Hanya tersedia untuk Owner/Platform)
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