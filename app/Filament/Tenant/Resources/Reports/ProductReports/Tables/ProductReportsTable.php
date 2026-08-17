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

                $baseTrxSub = DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->whereColumn('transaction_items.product_id', 'products.id')
                    // Membaca POS (sale) dan Invoice (invoice)
                    ->whereIn('transactions.type', ['sale', 'invoice'])
                    // Membaca Lunas (completed) dan Belum Lunas (pending)
                    ->whereIn('transactions.status', ['completed', 'pending'])
                    ->whereBetween('transactions.created_at', [$startDate, $endDate])
                    ->whereNull('transactions.deleted_at'); // Mencegah data terhapus ikut dihitung
                    
                if ($outletId) {
                    $baseTrxSub->where('transactions.outlet_id', $outletId);
                }

                $terjualSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.base_qty), 0)');
                $penjualanSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.subtotal), 0)');

                $hppSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.qty * transaction_items.cost_price), 0)');
                if ($outletId) {
                    $baseTrxSub->where('transactions.outlet_id', $outletId);
                }

                // =======================================================
                // PERBAIKAN: MENGGUNAKAN 'base_qty' AGAR SATUAN LUSIN DIHITUNG SEBAGAI 12 PCS
                // =======================================================
                $terjualSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.base_qty), 0)');

                $penjualanSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.subtotal), 0)');

                $hppSub = (clone $baseTrxSub)->selectRaw('COALESCE(SUM(transaction_items.qty * transaction_items.cost_price), 0)');

                $stokSub = DB::table('stocks')
                    ->whereColumn('stocks.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(stocks.qty), 0)');
                    
                if ($outletId) {
                    $stokSub->where('stocks.outlet_id', $outletId);
                }

                $query->select('products.*')
                    ->selectSub($terjualSub, 'terjual')
                    ->selectSub($penjualanSub, 'penjualan')
                    ->selectSub($hppSub, 'hpp_total')
                    ->selectSub($stokSub, 'stok_akhir');
            })
            ->columns([
                ImageColumn::make('image_url')->label('Foto')->square()->defaultImageUrl('https://placehold.co/100'),
                TextColumn::make('name')->label('Produk')->searchable()->sortable()->weight('bold')->description(fn ($record) => $record->sku ?? 'No SKU'),
                TextColumn::make('category.name')->label('Kategori')->sortable(),

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