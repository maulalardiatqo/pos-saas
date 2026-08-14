<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, \Filament\Tables\Contracts\HasTable $livewire) {
                /** @var \App\Models\User $user */
                $user = auth()->user();
                $isOwner = $user->isOwner() || $user->isPlatform();
                $tenantId = filament()->getTenant()->id;

                $startDate = $livewire->startDate ? Carbon::parse($livewire->startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
                $endDate = $livewire->endDate ? Carbon::parse($livewire->endDate)->endOfDay() : now()->endOfDay();
                $outletId = $livewire->outletId ?? ($isOwner ? null : $user->outlet_id);
                
                $query->where('products.company_id', $tenantId);

                // =======================================================
                // PERBAIKAN 1: BUAT BASE QUERY MURNI TANPA 'SELECT'
                // =======================================================
                $baseTrxSub = DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->whereColumn('transaction_items.product_id', 'products.id')
                    ->where('transactions.type', 'sale')
                    ->where('transactions.status', 'completed')
                    ->whereBetween('transactions.created_at', [$startDate, $endDate]);
                    
                if ($outletId) {
                    $baseTrxSub->where('transactions.outlet_id', $outletId);
                }

                // =======================================================
                // KLONING BASE QUERY AGAR KOLOM TIDAK BERTUMPUK (ERROR)
                // =======================================================
                // --- SUBQUERY TERJUAL (Kuantitas) ---
                $terjualSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.qty), 0)');

                // --- SUBQUERY PENJUALAN (Omset) ---
                $penjualanSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.subtotal), 0)');

                // --- SUBQUERY HPP (Modal) ---
                $hppSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.qty * transaction_items.cost_price), 0)');

                // --- SUBQUERY STOK AKHIR ---
                $stokSub = DB::table('stocks')
                    ->whereColumn('stocks.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(stocks.qty), 0)');
                    
                if ($outletId) {
                    $stokSub->where('stocks.outlet_id', $outletId);
                }

                // Masukkan semua subquery ke dalam query utama Product
                $query->select('products.*')
                    ->selectSub($terjualSub, 'terjual')
                    ->selectSub($penjualanSub, 'penjualan')
                    ->selectSub($hppSub, 'hpp_total')
                    ->selectSub($stokSub, 'stok_akhir');
            })
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Foto')
                    ->square()
                    ->defaultImageUrl('https://placehold.co/100'),

                TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->sku ?? 'No SKU'),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),

                // =======================================================
                // PERBAIKAN 2: PAKSA NILAI NULL MENJADI 0 DENGAN getStateUsing
                // =======================================================
                TextColumn::make('terjual')
                    ->label('Terjual')
                    ->getStateUsing(fn ($record) => (float) ($record->terjual ?? 0))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('penjualan')
                    ->label('Penjualan')
                    ->getStateUsing(fn ($record) => (float) ($record->penjualan ?? 0))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('hpp_total')
                    ->label('Total HPP')
                    ->getStateUsing(fn ($record) => (float) ($record->hpp_total ?? 0))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('laba_kotor')
                    ->label('Laba Kotor')
                    ->getStateUsing(fn ($record) => ((float)($record->penjualan ?? 0)) - ((float)($record->hpp_total ?? 0)))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('margin')
                    ->label('Margin')
                    ->getStateUsing(function ($record) {
                        $penjualan = (float)($record->penjualan ?? 0);
                        $laba = $penjualan - (float)($record->hpp_total ?? 0);
                        return $penjualan > 0 ? round(($laba / $penjualan) * 100, 1) : 0;
                    })
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state > 30 ? 'success' : ($state > 10 ? 'warning' : 'danger')),

                TextColumn::make('stok_akhir')
                    ->label('Stok Akhir')
                    ->getStateUsing(fn ($record) => (float) ($record->stok_akhir ?? 0))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 5 ? 'danger' : 'gray'),
            ])
            ->actions([]) 
            ->bulkActions([])
            ->defaultSort('penjualan', 'desc')
            ->striped();
    }
}