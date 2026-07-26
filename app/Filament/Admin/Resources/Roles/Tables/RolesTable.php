<?php

namespace App\Filament\Admin\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->color('info')
                    ->badge(),

                IconColumn::make('is_system')
                    ->label('Sistem')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user')
                    ->tooltip('Role bawaan sistem tidak bisa dihapus'),

                TextColumn::make('permissions.name')
                    ->label('Izin Fitur')
                    ->badge()
                    ->color('success')
                    ->listWithLineBreaks()
                    ->limitList(3) 
                    ->expandableLimitedList() 
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                
                DeleteAction::make()
                    ->hidden(fn (?Role $record) => $record?->is_system ?? false),
            ])
            ->bulkActions([
                //
            ]);
    }
}