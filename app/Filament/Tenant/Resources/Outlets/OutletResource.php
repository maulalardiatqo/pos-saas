<?php

namespace App\Filament\Tenant\Resources\Outlets;

use App\Filament\Tenant\Resources\Outlets\Pages\CreateOutlet;
use App\Filament\Tenant\Resources\Outlets\Pages\EditOutlet;
use App\Filament\Tenant\Resources\Outlets\Pages\ListOutlets;
use App\Filament\Tenant\Resources\Outlets\Schemas\OutletForm;
use App\Filament\Tenant\Resources\Outlets\Tables\OutletsTable;
use App\Models\Outlet;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OutletResource extends Resource
{
    protected static ?string $model = Outlet::class;

    protected static ?string $slug = 'cabang';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pengguna'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan (Hak Akses Murni - Limit diurus di Pages)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        // Asumsi kode permission Anda 'outlets.view'
        return $user->hasPermission('outlets.view'); 
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('outlets.create');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('outlets.edit');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('outlets.delete');
    }

    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return OutletForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutletsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutlets::route('/'),
            'create' => CreateOutlet::route('/create'),
            'edit' => EditOutlet::route('/{record}/edit'),
        ];
    }
}