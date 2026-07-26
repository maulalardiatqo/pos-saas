<?php

namespace App\Filament\Tenant\Resources\StockAdjustments;

use App\Models\StockAdjustment;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

// Panggil class skema yang baru saja dibuat
use App\Filament\Tenant\Resources\StockAdjustments\Schemas\StockAdjustmentForm;
use App\Filament\Tenant\Resources\StockAdjustments\Tables\StockAdjustmentsTable;

use App\Filament\Tenant\Resources\StockAdjustments\Pages\ListStockAdjustments;
use App\Filament\Tenant\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Filament\Tenant\Resources\StockAdjustments\Pages\EditStockAdjustment;
use App\Filament\Tenant\Resources\StockAdjustments\Pages\ViewStockAdjustment;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $slug = 'inventory/adjustments';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventori & Stok';
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Otorisasi Berantai (Cascading Protection)
    |--------------------------------------------------------------------------
    */
    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        if (!$tenant || data_get($tenant->subscriptionPlan?->features, 'modules.inventory') !== true) {
            return false;
        }
        if (data_get($tenant->subscriptionPlan?->features, 'inventory.adjustment') !== true) {
            return false;
        }

        $user = auth()->user();
        return $user && $user->hasPermission('inventory.adjustment');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny(); 
    }

    public static function canEdit(Model $record): bool
    {
        if (in_array($record->status, ['completed', 'cancelled'])) {
            return false;
        }
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        if ($record->status === 'completed') {
            return false;
        }
        return static::canViewAny();
    }

    public static function can(\UnitEnum|string $action, ?Model $record = null): bool
    {
        $actionName = $action instanceof \UnitEnum ? $action->name : $action;

        return match ($actionName) {
            'viewAny' => static::canViewAny(),
            'create'  => static::canCreate(),
            'update', 'edit' => $record ? static::canEdit($record) : static::canViewAny(),
            'delete'  => $record ? static::canDelete($record) : static::canViewAny(),
            'view'    => $record ? static::canView($record) : static::canViewAny(),
            default   => parent::can($action, $record),
        };
    }

    public static function form(Schema $schema): Schema
    {
        return StockAdjustmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockAdjustmentsTable::configure($table);
    }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && !$user->isOwner() && !$user->isPlatform()) {
            $query->where('outlet_id', $user->outlet_id);
        }

        return $query;
    }
    public static function getPages(): array
    {
        return [
            'index' => ListStockAdjustments::route('/'),
            'create' => CreateStockAdjustment::route('/create'),
            'edit' => EditStockAdjustment::route('/{record}/edit'),
            'view' => ViewStockAdjustment::route('/{record}'),
        ];
    }
}