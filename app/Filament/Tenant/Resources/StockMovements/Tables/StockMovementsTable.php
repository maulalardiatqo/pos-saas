<?php

namespace App\Filament\Tenant\Resources\StockMovements\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('outlet.name')
                    ->label('Cabang')
                    ->searchable()
                    ->visible(fn () => auth()->user()->isOwner() || auth()->user()->isPlatform()),

                TextColumn::make('type')
                    ->label('Jenis Transaksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'adjustment' => 'warning',
                        'sale'       => 'success',
                        'purchase'   => 'info',
                        'return'     => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                TextColumn::make('quantity')
                    ->label('Mutasi Qty')
                    ->numeric()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('balance_after')
                    ->label('Saldo Akhir')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('remarks')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                
                // FILTER OUTLET: Hanya dimunculkan untuk Owner/Platform
                SelectFilter::make('outlet_id')
                    ->label('Filter Cabang')
                    ->relationship('outlet', 'name', fn (Builder $query) => $query->where('company_id', filament()->getTenant()?->id))
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->isOwner() || auth()->user()->isPlatform()),

                SelectFilter::make('type')
                    ->label('Filter Jenis')
                    ->options([
                        'adjustment' => 'Adjustment',
                        'sale'       => 'Penjualan (POS)',
                        'purchase'   => 'Pembelian',
                        'return'     => 'Retur',
                    ]),
                    
                SelectFilter::make('product_id')
                    ->label('Filter Produk')
                    ->relationship('product', 'name', fn (Builder $query) => $query->where('company_id', filament()->getTenant()?->id))
                    ->searchable()
                    ->preload(),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}