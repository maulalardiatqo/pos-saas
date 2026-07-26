<?php

namespace App\Filament\Tenant\Resources\StockBalances\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class StockBalancesTable
{
    public static function configure(Table $table): Table
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();
        $tenantId = filament()->getTenant()->id;

        // Ambil daftar semua outlet jika yang login adalah Owner
        $outlets = $isOwner ? \App\Models\Outlet::where('company_id', $tenantId)->get() : collect();

        // ========================================================
        // 1. SUSUN KOLOM DASAR (Sama untuk semua role)
        // ========================================================
        $columns = [

            TextColumn::make('name')
                ->label('Nama Produk')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            TextColumn::make('sku')
                ->label('SKU')
                ->searchable()
                ->copyable()
                ->color('gray'),

            TextColumn::make('category.name')
                ->label('Kategori')
                ->sortable(),
        ];

        // ========================================================
        // 2. KOLOM KHUSUS OWNER (Grup Cabang & Total)
        // ========================================================
        if ($isOwner) {
            $outletColumns = [];

            // Bikin kolom untuk masing-masing cabang
            foreach ($outlets as $outlet) {
                $outletColumns[] = TextColumn::make('stock_outlet_' . $outlet->id)
                    ->label($outlet->name)
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 5 ? 'danger' : ($state <= 20 ? 'warning' : 'success'));
            }

            // Masukkan kolom-kolom cabang ke dalam Grup "Sisa Stock"
            if (count($outletColumns) > 0) {
                $columns[] = ColumnGroup::make('Sisa Stock', $outletColumns);
            }

            // Kolom Total Semua Cabang (Dihitung di memori PHP)
            $columns[] = TextColumn::make('total_stock')
                ->label('Total Sisa Stock')
                ->getStateUsing(function ($record) use ($outlets) {
                    $total = 0;
                    foreach ($outlets as $outlet) {
                        $total += (float) $record->{'stock_outlet_' . $outlet->id};
                    }
                    return $total;
                })
                ->badge()
                ->color('primary') // Warna biru/utama untuk total
                ->weight('bold');
        } 
        // ========================================================
        // 3. KOLOM KHUSUS KASIR (Hanya 1 Outlet)
        // ========================================================
        else {
            $columns[] = TextColumn::make('current_stock')
                ->label('Sisa Stok')
                ->numeric()
                ->sortable() 
                ->badge()
                ->color(fn ($state) => $state <= 5 ? 'danger' : ($state <= 20 ? 'warning' : 'success'))
                ->suffix(fn ($record) => ' ' . ($record->baseUom->name ?? ''));
        }

        // Kolom HPP (Sembunyi by default)
        $columns[] = TextColumn::make('cost_price')
            ->label('HPP / Modal')
            ->money('IDR', locale: 'id')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        // ========================================================
        // 4. KONFIGURASI TABEL & INJEKSI QUERY
        // ========================================================
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isOwner, $outlets, $user) {
                $query->addSelect('products.*');

                if ($isOwner) {
                    // Inject subquery untuk SETIAP CABANG secara paralel
                    foreach ($outlets as $outlet) {
                        $latestStockSubquery = \App\Models\StockMovement::select('balance_after')
                            ->whereColumn('product_id', 'products.id')
                            ->where('outlet_id', $outlet->id)
                            ->latest('created_at')
                            ->limit(1);

                        $query->selectSub($latestStockSubquery, 'stock_outlet_' . $outlet->id);
                    }
                } else {
                    // Inject subquery khusus 1 cabang kasir
                    $latestStockSubquery = \App\Models\StockMovement::select('balance_after')
                        ->whereColumn('product_id', 'products.id')
                        ->where('outlet_id', $user->outlet_id)
                        ->latest('created_at')
                        ->limit(1);
                    
                    $query->selectSub($latestStockSubquery, 'current_stock');
                }
            })
            ->columns($columns)
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                // Filter Dropdown Outlet DIHAPUS karena Owner sudah melihat semua dari tabel
            ])
            ->actions([]) 
            ->bulkActions([]);
    }
}