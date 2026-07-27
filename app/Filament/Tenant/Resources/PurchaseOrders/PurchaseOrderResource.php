<?php

namespace App\Filament\Tenant\Resources\PurchaseOrders;

use App\Filament\Tenant\Resources\PurchaseOrders\Pages;
use App\Filament\Tenant\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Tenant\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class PurchaseOrderResource extends Resource
{
    // Kunci ke model PurchaseOrder yang sudah memiliki Global Scope 'purchaseorder'
    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?string $navigationLabel = 'Belanhja (PO)';
    protected static ?string $pluralLabel = 'Daftar Belanja';
    protected static ?string $slug = 'purchase-orders';

    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\Company $company */
        $company = filament()->getTenant();
        
        if (!$company) {
            return false;
        }

        return $company->hasFeature('purchase.po'); 
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::table($table);
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}