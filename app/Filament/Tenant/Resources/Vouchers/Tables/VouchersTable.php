<?php

namespace App\Filament\Tenant\Resources\Vouchers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class VouchersTable
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
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nama Promo')
                    ->searchable(),

                TextColumn::make('discount_value')
                    ->label('Potongan')
                    ->sortable()
                    ->formatStateUsing(function ($record, $state) {
                        return $record->discount_type === 'percentage' 
                            ? number_format($state, 0) . '%' 
                            : 'Rp ' . number_format($state, 0, ',', '.');
                    }),

                TextColumn::make('used_count')
                    ->label('Terpakai')
                    ->alignCenter()
                    ->description(fn ($record) => $record->usage_limit ? "Dari {$record->usage_limit} kuota" : 'Kuota Unlimited'),

                TextColumn::make('end_date')
                    ->label('Kedaluwarsa')
                    ->dateTime('d M Y H:i')
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