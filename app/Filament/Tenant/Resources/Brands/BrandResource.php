<?php

namespace App\Filament\Tenant\Resources\Brands;

use App\Models\Brand;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

// Import Schema & Table yang baru dibuat
use App\Filament\Tenant\Resources\Brands\Schemas\BrandForm;
use App\Filament\Tenant\Resources\Brands\Tables\BrandsTable;

// Import Pages
use App\Filament\Tenant\Resources\Brands\Pages\ListBrands;
use App\Filament\Tenant\Resources\Brands\Pages\CreateBrand;
use App\Filament\Tenant\Resources\Brands\Pages\EditBrand;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $slug = 'brand';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (RBAC & Feature Toggling) - Pendekatan Ketat
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'products.brand') === true;
        $hasPermission = $user->hasPermission('products.brand');

        return $isFeatureEnabled && $hasPermission;
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi UI
    |--------------------------------------------------------------------------
    */
    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}