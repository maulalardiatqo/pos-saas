<?php

namespace App\Filament\Tenant\Resources\Memberships\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Level Membership')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('min_points')
                    ->label('Syarat Poin')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('discount_percentage')
                    ->label('Diskon')
                    ->suffix('%')
                    ->sortable(),
                
                TextColumn::make('customers_count')
                    ->counts('customers') 
                    ->label('Jml Pelanggan')
                    ->badge(),
                
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}