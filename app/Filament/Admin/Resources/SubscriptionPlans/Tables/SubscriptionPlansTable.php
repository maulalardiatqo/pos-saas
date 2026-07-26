<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('Nama Paket')
                    ->searchable()
                    ->sortable(),


                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),


                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),


                TextColumn::make('billing_cycle')
                    ->label('Billing')
                    ->formatStateUsing(fn($state) =>
                        ucfirst($state)
                    ),


                TextColumn::make('features.limits.outlets')
                    ->label('Maks. Outlet')
                    ->alignCenter(),


                TextColumn::make('features.limits.users')
                    ->label('Maks. User')
                    ->alignCenter(),


                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) =>
                        $state ? 'Aktif' : 'Nonaktif'
                    ),


                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

            ]);
    }
}