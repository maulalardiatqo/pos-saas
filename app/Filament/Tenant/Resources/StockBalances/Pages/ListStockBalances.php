<?php

namespace App\Filament\Tenant\Resources\StockBalances\Pages;

use App\Filament\Tenant\Resources\StockBalances\StockBalanceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListStockBalances extends ListRecords
{
    protected static string $resource = StockBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua Product')
                ->icon('heroicon-m-squares-2x2'),

            'habis' => Tab::make('Product Habis')
                ->icon('heroicon-m-exclamation-triangle')
                ->modifyQueryUsing(function (Builder $query) {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    $isOwner = $user->isOwner() || $user->isPlatform();

                    // Filter produk yang TIDAK MEMILIKI stok > 0 di tabel stocks
                    // (Berlaku untuk stok = 0 atau yang belum pernah di-set stoknya / null)
                    return $query->whereNotExists(function (\Illuminate\Database\Query\Builder $subquery) use ($user, $isOwner) {
                        $subquery->select(DB::raw(1))
                            ->from('stocks')
                            ->whereColumn('stocks.product_id', 'products.id')
                            ->where('stocks.qty', '>', 0)
                            ->when(!$isOwner, function ($q) use ($user) {
                                // Jika staf, pastikan hanya mengecek stok di cabangnya saja
                                $q->where('stocks.outlet_id', $user->outlet_id);
                            });
                    });
                }),
        ];
    }
}
