<?php

namespace App\Filament\Tenant\Resources\Vouchers;

use App\Models\Voucher;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Tenant\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Tenant\Resources\Vouchers\Tables\VouchersTable;

use App\Filament\Tenant\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Tenant\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Tenant\Resources\Vouchers\Pages\EditVoucher;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $slug = 'vouchers';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-ticket';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CRM & Pelanggan';
    }

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();
        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'crm.voucher') === true;
        $hasPermission = $user->hasPermission('crm.voucher');

        return $isFeatureEnabled && $hasPermission;
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'edit' => EditVoucher::route('/{record}/edit'),
        ];
    }
}