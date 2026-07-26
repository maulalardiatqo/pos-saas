<?php

namespace App\Filament\Tenant\Resources\StockBalances;

use App\Filament\Tenant\Resources\StockBalances\Pages\ListStockBalances;
use App\Filament\Tenant\Resources\StockBalances\Schemas\StockBalanceForm;
use App\Filament\Tenant\Resources\StockBalances\Schemas\StockBalanceInfolist;
use App\Filament\Tenant\Resources\StockBalances\Tables\StockBalancesTable;
use App\Models\Product; 
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockBalanceResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $slug = 'stock-balances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    public static function getNavigationGroup(): ?string
    {
        return 'Inventori & Stok';
    }
    protected static ?string $navigationLabel = 'Saldo Stok';
    protected static ?string $pluralLabel = 'Daftar Saldo Stok';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return StockBalanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockBalancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockBalances::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([ SoftDeletingScope::class ])
            ->where('item_type', 'goods');

        $outletId = auth()->user()->outlet_id ?? \App\Models\Outlet::where('company_id', filament()->getTenant()->id)->value('id');

        $latestStockSubquery = \App\Models\StockMovement::select('balance_after')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $outletId)
            ->latest('created_at')
            ->limit(1);

        return $query->addSelect('products.*')->selectSub($latestStockSubquery, 'current_stock');
    }
}