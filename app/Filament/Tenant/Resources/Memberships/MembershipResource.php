<?php

namespace App\Filament\Tenant\Resources\Memberships;

use App\Models\Membership;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

// Import Schema & Table
use App\Filament\Tenant\Resources\Memberships\Schemas\MembershipForm;
use App\Filament\Tenant\Resources\Memberships\Tables\MembershipsTable;

class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $slug = 'membership';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-star'; // Ikon bintang cocok untuk loyalty
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CRM & Pelanggan'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Hak Akses & Feature Toggling (Berdasarkan Paket Toko)
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        // 1. Cek apakah fitur Membership menyala di paket toko ini
        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'crm.membership') === true;
        
        // 2. Cek izin RBAC karyawan
        $hasPermission = $user->hasPermission('crm.membership');

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
        return MembershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\Memberships\Pages\ManageMemberships::route('/'),
        ];
    }
}