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

                // =========================================================================
                // KOLOM BARU: NOMOR TRANSAKSI (KLIKABEL)
                // =========================================================================
                TextColumn::make('reference_id')
                    ->label('No. Transaksi / Referensi')
                    ->formatStateUsing(function (string $state) {
                        // Ambil nomor transaksi dari database
                        $transaction = \App\Models\Transaction::find($state);
                        return $transaction ? $transaction->transaction_number : '-';
                    })
                    ->url(function (string $state) {
                        $transaction = \App\Models\Transaction::find($state);
                        if (!$transaction) return null;

                        // Arahkan URL ke halaman View yang tepat berdasarkan tipe transaksi
                        return match ($transaction->type) {
                            'purchaseorder' => \App\Filament\Tenant\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('view', ['record' => $state]),
                            'refund'        => \App\Filament\Tenant\Resources\PurchaseReturns\PurchaseReturnResource::getUrl('view', ['record' => $state]),
                            'invoice'       => \App\Filament\Tenant\Resources\SalesInvoices\SalesInvoiceResource::getUrl('view', ['record' => $state]),
                            // Jika ada resource lain (misal: penjualan tunai/POS), Anda bisa menambahkannya di sini
                            default => null,
                        };
                    })
                    ->openUrlInNewTab()
                    ->color('info')
                    ->weight('bold')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereIn('reference_id', function ($q) use ($search) {
                            $q->select('id')
                              ->from('transactions')
                              ->where('transaction_number', 'like', "%{$search}%");
                        });
                    }),

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