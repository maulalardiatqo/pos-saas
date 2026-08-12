<?php

namespace App\Filament\Tenant\Resources\StockBalances\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockBalancesTable
{
    public static function configure(Table $table): Table
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();
        $tenantId = filament()->getTenant()->id;

        $outlets = $isOwner ? \App\Models\Outlet::where('company_id', $tenantId)->get() : collect();

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

        if ($isOwner) {
            $outletColumns = [];

            foreach ($outlets as $outlet) {
                $outletColumns[] = TextColumn::make('stock_outlet_' . $outlet->id)
                    ->label($outlet->name)
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => (float) $state <= 5 ? 'danger' : ((float) $state <= 20 ? 'warning' : 'success'));
            }

            if (count($outletColumns) > 0) {
                $columns[] = ColumnGroup::make('Sisa Stock', $outletColumns);
            }

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
                ->color('primary')
                ->weight('bold');
        } 
        else {
            $columns[] = TextColumn::make('current_stock')
                ->label('Sisa Stok')
                ->numeric()
                ->sortable() 
                ->badge()
                ->color(fn ($state) => (float) $state <= 5 ? 'danger' : ((float) $state <= 20 ? 'warning' : 'success'))
                ->suffix(fn ($record) => ' ' . ($record->baseUom->name ?? ''));
        }

        $columns[] = TextColumn::make('cost_price')
            ->label('HPP / Modal')
            ->money('IDR', locale: 'id')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isOwner, $outlets, $user) {
                $query->addSelect('products.*');

                if ($isOwner) {
                    foreach ($outlets as $outlet) {
                        $stockSubquery = \App\Models\Stock::selectRaw('COALESCE(qty, 0)')
                            ->whereColumn('product_id', 'products.id')
                            ->where('outlet_id', $outlet->id)
                            ->limit(1);

                        $query->selectSub($stockSubquery, 'stock_outlet_' . $outlet->id);
                    }
                } else {
                    $stockSubquery = \App\Models\Stock::selectRaw('COALESCE(qty, 0)')
                        ->whereColumn('product_id', 'products.id')
                        ->where('outlet_id', $user->outlet_id)
                        ->limit(1);
                    
                    $query->selectSub($stockSubquery, 'current_stock');
                }
            })
            ->columns($columns)
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([]) 
            ->bulkActions([]);
    }
}