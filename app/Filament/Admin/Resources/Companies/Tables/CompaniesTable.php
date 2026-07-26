<?php

namespace App\Filament\Admin\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subscriptionPlan.name')
                    ->label('Paket Langganan')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge() // Mengubah teks status menjadi badge berwarna
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',      // Hijau
                        'suspended' => 'danger',    // Merah
                        'expired' => 'warning',     // Kuning/Oranye
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Masa Berlaku')
                    ->date('d M Y') // Format tanggal: 13 Jul 2026
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true) // Sembunyikan default, bisa dimunculkan via opsi tabel
                    ->sortable(),
            ]);
    }
}
