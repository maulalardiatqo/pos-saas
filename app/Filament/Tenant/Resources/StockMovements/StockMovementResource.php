<?php

namespace App\Filament\Tenant\Resources\StockMovements;

use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // <-- Tambahan wajib untuk getEloquentQuery

use App\Filament\Tenant\Resources\StockMovements\Tables\StockMovementsTable;
use App\Filament\Tenant\Resources\StockMovements\Pages\ListStockMovements;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $slug = 'inventory/movements';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventori & Stok';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Riwayat Stok';
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Filter Data (Multi-Outlet)
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Jika user BUKAN Owner dan BUKAN Platform, 
        // paksa query hanya mengambil riwayat stok dari outlet tempat ia bekerja
        if ($user && !$user->isOwner() && !$user->isPlatform()) {
            $query->where('outlet_id', $user->outlet_id);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan: Mode Read-Only Total
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        // Cek JSON langganan: Apakah modul inventori dan fitur history aktif?
        if (!$tenant || data_get($tenant->subscriptionPlan?->features, 'modules.inventory') !== true) {
            return false;
        }
        if (data_get($tenant->subscriptionPlan?->features, 'inventory.history') !== true) {
            return false;
        }

        $user = auth()->user();
        return $user && ($user->isOwner() || $user->hasPermission('inventory.history'));
    }

    // Kunci MATI fungsi Create, Edit, dan Delete untuk semua orang tanpa terkecuali
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function can(\UnitEnum|string $action, ?Model $record = null): bool
    {
        $actionName = $action instanceof \UnitEnum ? $action->name : $action;

        return match ($actionName) {
            'viewAny' => static::canViewAny(),
            'view'    => static::canViewAny(),
            'create', 'update', 'edit', 'delete' => false, 
            default   => parent::can($action, $record),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */
    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}