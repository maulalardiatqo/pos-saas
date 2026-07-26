<?php

namespace App\Filament\Tenant\Resources\Customers;

use App\Filament\Tenant\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Tenant\Resources\Customers\Pages\EditCustomer;
use App\Filament\Tenant\Resources\Customers\Pages\ListCustomers;
use App\Filament\Tenant\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Tenant\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use Filament\Facades\Filament; // Import untuk menangani fitur multi-tenancy
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'pelanggan';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Kontak'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Otorisasi Berantai (Cascading Protection)
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        // Cek apakah modul pelanggan aktif di paket langganan tenant
        if (!$tenant || !$tenant->hasFeature('modules.customers')) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('customers.view');
    }

    public static function canCreate(): bool
    {
        // Jika tidak lolos cek view induk, otomatis tolak pembuatan data
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('customers.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('customers.edit');
    }

    public static function canDelete(Model $record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user && $user->hasPermission('customers.delete');
    }

    public static function can(\UnitEnum|string $action, ?Model $record = null): bool
    {
        // Mengonversi aksi ke string jika Filament mengirimkan format UnitEnum
        $actionName = $action instanceof \UnitEnum ? $action->name : $action;

        return match ($actionName) {
            'viewAny' => static::canViewAny(),
            'create'  => static::canCreate(),
            'update', 'edit' => $record ? static::canEdit($record) : static::canViewAny(),
            'delete'  => $record ? static::canDelete($record) : static::canViewAny(),
            default   => parent::can($action, $record),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi UI
    |--------------------------------------------------------------------------
    */
    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}