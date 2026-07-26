<?php

namespace App\Filament\Tenant\Resources\GiftCards\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class GiftCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('card_number')
                    ->label('Nomor Kartu')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->weight('bold'),

                TextColumn::make('customer.name')
                    ->label('Pemilik')
                    ->searchable()
                    ->placeholder('Umum / Tanpa Nama'),

                TextColumn::make('balance')
                    ->label('Sisa Saldo')
                    ->numeric()
                    ->sortable()
                    ->money('IDR', locale: 'id'),

                TextColumn::make('expiry_date')
                    ->label('Masa Berlaku')
                    ->date('d M Y')
                    ->placeholder('Selamanya')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Status'),
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