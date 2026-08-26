<?php

namespace App\Filament\Tenant\Resources\Categories;

use App\Filament\Tenant\Resources\Categories\Pages\CreateCategory;
use App\Filament\Tenant\Resources\Categories\Pages\EditCategory;
use App\Filament\Tenant\Resources\Categories\Pages\ListCategories;
use App\Filament\Tenant\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Tenant\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $tenant = \Filament\Facades\Filament::getTenant();
        
        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'products.category') === true;
        
        $hasPermission = $user->hasPermission('products.category');

        return $isFeatureEnabled && $hasPermission;
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('categories.edit');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('categories.delete');
    }


    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}