<?php

namespace App\Filament\Tenant\Resources\Suppliers;

use App\Filament\Tenant\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Tenant\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Tenant\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Tenant\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Tenant\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; 

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $slug = 'pemasok';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Kontak'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (RBAC)
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermission('suppliers.view');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermission('suppliers.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasPermission('suppliers.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasPermission('suppliers.manage');
    }

    /*
    |--------------------------------------------------------------------------
    | Filter Data (Multi-Outlet)
    |--------------------------------------------------------------------------
    */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Jika Owner atau Platform, tampilkan semua supplier
        if ($user && ($user->isOwner() || $user->isPlatform())) {
            return $query;
        }

        // Karyawan biasa: hanya lihat supplier global (outlet_id null) atau supplier dari cabangnya sendiri
        return $query->where(function ($q) use ($user) {
            $q->whereNull('outlet_id')
              ->orWhere('outlet_id', $user->outlet_id);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi UI
    |--------------------------------------------------------------------------
    */
    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}