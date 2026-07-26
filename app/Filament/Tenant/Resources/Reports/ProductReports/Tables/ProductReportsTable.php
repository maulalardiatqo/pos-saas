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
            // 1. INJEKSI SUBQUERY & AMBIL FILTER DARI LIVEWIRE BLADE
            ->modifyQueryUsing(function (Builder $query, \Filament\Tables\Contracts\HasTable $livewire) {
                /** @var \App\Models\User $user */
                $user = auth()->user();
                $isOwner = $user->isOwner() || $user->isPlatform();
                $tenantId = filament()->getTenant()->id;

                // Ambil nilai filter dari properti Livewire di ListProductReports
                $startDate = $livewire->startDate ? Carbon::parse($livewire->startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
                $endDate = $livewire->endDate ? Carbon::parse($livewire->endDate)->endOfDay() : now()->endOfDay();
                $outletId = $livewire->outletId ?? ($isOwner ? null : $user->outlet_id);
                
                $query->where('products.company_id', $tenantId);

                // --- SUBQUERY TERJUAL (Kuantitas) ---
                $terjualSub = DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->whereColumn('transaction_items.product_id', 'products.id')
                    ->where('transactions.type', 'sale')
                    ->where('transactions.status', 'completed')
                    ->whereBetween('transactions.created_at', [$startDate, $endDate])
                    ->select(DB::raw('COALESCE(SUM(transaction_items.qty), 0)'));
                if ($outletId) $terjualSub->where('transactions.outlet_id', $outletId);

                // --- SUBQUERY PENJUALAN (Omset) ---
                $penjualanSub = clone $terjualSub;
                $penjualanSub->select(DB::raw('COALESCE(SUM(transaction_items.subtotal), 0)'));

                // --- SUBQUERY HPP (Modal) ---
                $hppSub = clone $terjualSub;
                $hppSub->select(DB::raw('COALESCE(SUM(transaction_items.qty * transaction_items.cost_price), 0)'));

                // --- SUBQUERY STOK AKHIR ---
                $stokSub = DB::table('stock_movements')
                    ->whereColumn('stock_movements.product_id', 'products.id')
                    ->orderByDesc('created_at')
                    ->select('balance_after')
                    ->limit(1);

                // Masukkan semua subquery ke dalam query utama Product
                $query->select('products.*')
                    ->selectSub($terjualSub, 'terjual')
                    ->selectSub($penjualanSub, 'penjualan')
                    ->selectSub($hppSub, 'hpp_total')
                    ->selectSub($stokSub, 'stok_akhir');
            })
            // 2. DEFINISI KOLOM TABEL SESUAI DESAIN UI
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

                TextColumn::make('terjual')
                    ->label('Terjual')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('penjualan')
                    ->label('Penjualan')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('hpp_total')
                    ->label('Total HPP')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('laba_kotor')
                    ->label('Laba Kotor')
                    ->getStateUsing(fn ($record) => (float)$record->penjualan - (float)$record->hpp_total)
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('margin')
                    ->label('Margin')
                    ->getStateUsing(function ($record) {
                        $penjualan = (float)$record->penjualan;
                        $laba = (float)$record->penjualan - (float)$record->hpp_total;
                        return $penjualan > 0 ? round(($laba / $penjualan) * 100, 1) : 0;
                    })
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state > 30 ? 'success' : ($state > 10 ? 'warning' : 'danger')),

                TextColumn::make('stok_akhir')
                    ->label('Stok Akhir')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 5 ? 'danger' : 'gray'),
            ])
            // 3. MENGHILANGKAN TOMBOL ACTION (Read Only)
            ->actions([]) 
            ->bulkActions([])
            ->defaultSort('penjualan', 'desc')
            ->striped();
    }
}