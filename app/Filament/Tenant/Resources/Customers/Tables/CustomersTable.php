<?php

namespace App\Filament\Tenant\Resources\Customers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
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

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-'),

                // DIHUBUNGKAN KE RELASI TABEL MEMBERSHIP
                TextColumn::make('membership.name')
                    ->label('Level Membership')
                    ->badge()
                    ->color('warning') // Warna emas/kuning elegan untuk status membership
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