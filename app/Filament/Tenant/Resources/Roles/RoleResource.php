<?php

namespace App\Filament\Tenant\Resources\Roles;

use App\Filament\Tenant\Resources\Roles\Pages\CreateRole;
use App\Filament\Tenant\Resources\Roles\Pages\EditRole;
use App\Filament\Tenant\Resources\Roles\Pages\ListRoles;
use App\Filament\Tenant\Resources\Roles\Schemas\RoleForm;
use App\Filament\Tenant\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Facades\Filament; // <-- Wajib di-import untuk mengambil data Tenant aktif
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'jabatan';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pengguna'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Hak Akses & Validasi Paket Langganan (Subscription Plan)
    |--------------------------------------------------------------------------
    */
    
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        
        // 1. Cek Gembok Paket: Apakah fitur manajemen role/jabatan aktif di plan ini?
        // Kita beri default 'true' jika fitur ini dianggap sebagai fitur dasar (core) semua paket
        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'modules.roles', true) === true;
        
        // 2. Cek Hak Akses Karyawan (RBAC)
        $hasPermission = auth()->user()->hasPermission('roles.view'); 
        
        return $isFeatureEnabled && $hasPermission;
    }

    public static function canCreate(): bool
    {
        // Menyamakan gerbang keamanan dengan aturan view utama
        return static::canViewAny() && auth()->user()->hasPermission('roles.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && auth()->user()->hasPermission('roles.edit');
    }

    public static function canDelete(Model $record): bool
    {
        if ($record->is_system) {
            return false;
        }
        
        return static::canViewAny() && auth()->user()->hasPermission('roles.delete');
    }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Skema Form & Tabel (Modular)
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}