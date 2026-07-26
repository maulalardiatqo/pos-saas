<?php

namespace App\Filament\Tenant\Resources\Products;

use App\Filament\Tenant\Resources\Products\Pages\CreateProduct;
use App\Filament\Tenant\Resources\Products\Pages\EditProduct;
use App\Filament\Tenant\Resources\Products\Pages\ListProducts;
use App\Filament\Tenant\Resources\Products\Schemas\ProductForm;
use App\Filament\Tenant\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cube';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Otorisasi Berantai (Cascading Protection)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        // Cek apakah modul produk aktif di paket langganan tenant
        if (!$tenant || !$tenant->hasFeature('modules.products')) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('products.view');
    }

    public static function canCreate(): bool
    {
        // Jika tidak lolos cek view utama / paket, langsung tolak tombol create
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('products.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('products.edit');
    }

    public static function canDelete(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('products.delete');
    }

    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}