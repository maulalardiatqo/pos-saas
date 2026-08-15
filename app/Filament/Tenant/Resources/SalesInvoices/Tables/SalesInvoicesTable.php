<?php

namespace App\Filament\Tenant\Resources\SalesInvoices\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')->label('No. Invoice')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y')->sortable(),
                TextColumn::make('customer.name')->label('Pelanggan')->searchable(),
                TextColumn::make('grand_total')->label('Total Tagihan')->money('IDR', locale: 'id'),
                TextColumn::make('amount_paid')->label('Telah Dibayar')->money('IDR', locale: 'id')->color('success'),
                TextColumn::make('amount_change')->label('Sisa Tagihan')
                    ->getStateUsing(fn ($record) => abs($record->amount_change))
                    ->money('IDR', locale: 'id')->color('danger')->weight('bold'),
                TextColumn::make('status')->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state === 'completed' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state === 'completed' ? 'LUNAS' : 'BELUM LUNAS'),
            ])
            ->actions([
                ViewAction::make()->label('Buka Invoice / Bayar'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}