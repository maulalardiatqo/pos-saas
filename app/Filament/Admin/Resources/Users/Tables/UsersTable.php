<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('role.name')
                    ->label('Role')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('outlet.name')
                    ->label('Penempatan Outlet')
                    ->placeholder('Semua Outlet / Pusat') 
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}