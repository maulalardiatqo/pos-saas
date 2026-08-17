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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([ SoftDeletingScope::class ])
            // Hanya tampilkan Barang Fisik (Bukan Jasa)
            ->where('item_type', 'goods')
            // KUNCI PERBAIKAN: Hanya tampilkan Produk Standar (Mengecualikan Bundle / Recipe)
            ->where('product_type', 'standard');
    }
}