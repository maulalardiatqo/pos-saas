<?php

namespace App\Filament\Tenant\Resources\Assets\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';
    protected static ?string $title = 'Riwayat Pergerakan Aset';
    protected static \BackedEnum|string|null $icon = 'heroicon-o-clipboard-document-list';

    public function form(Schema $schema): Schema
    {
        // Dikosongkan karena log berjalan otomatis (Read-Only)
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action_type')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dilakukan Oleh')
                    ->searchable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created'        => 'Aset Didaftarkan',
                        'moved'          => 'Mutasi Outlet',
                        'status_changed' => 'Perubahan Kondisi',
                        'maintenance'    => 'Servis',
                        default          => strtoupper($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'created'        => 'success',
                        'moved'          => 'warning',
                        'status_changed' => 'info',
                        'maintenance'    => 'danger',
                        default          => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fromOutlet.name')
                    ->label('Dari Outlet')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('toOutlet.name')
                    ->label('Ke Outlet')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Catatan')
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}