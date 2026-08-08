<?php

namespace App\Filament\Tenant\Resources\StockTransfers;

use App\Models\StockTransfer;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema; // <-- DIUBAH KE SCHEMA
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Tenant\Resources\StockTransfers\Schemas\StockTransferForm;
use App\Filament\Tenant\Resources\StockTransfers\Tables\StockTransfersTable;

use App\Filament\Tenant\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\Tenant\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Tenant\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\Tenant\Resources\StockTransfers\Pages\ViewStockTransfer;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static ?string $slug = 'inventory/stock-transfers';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventori & Stok';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Transfer Stock';
    }


    /*
    |--------------------------------------------------------------------------
    | Keamanan & Izin Akses
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        if (!$tenant || data_get($tenant->subscriptionPlan?->features, 'modules.inventory') !== true) {
            return false;
        }

        $user = auth()->user();
        return $user && ($user->isOwner() || $user->hasPermission('inventory.transfer'));
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    
    public static function canEdit(Model $record): bool 
    { 
        return static::canViewAny() && $record->status === 'draft'; 
    }
    
    public static function canDelete(Model $record): bool 
    { 
        return static::canViewAny() && $record->status === 'draft'; 
    }

    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */
    public static function form(Schema $schema): Schema // <-- DIUBAH KE SCHEMA
    {
        return StockTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'view' => ViewStockTransfer::route('/{record}'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }
}