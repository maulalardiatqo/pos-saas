<?php

namespace App\Filament\Tenant\Resources\Uoms;

use App\Filament\Tenant\Resources\Uoms\Pages\CreateUom;
use App\Filament\Tenant\Resources\Uoms\Pages\EditUom;
use App\Filament\Tenant\Resources\Uoms\Pages\ListUoms;
use App\Filament\Tenant\Resources\Uoms\Schemas\UomForm;
use App\Filament\Tenant\Resources\Uoms\Tables\UomsTable;
use App\Models\Uom;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UomResource extends Resource
{
    protected static ?string $model = Uom::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-scale';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan (Gembok Ganda: Fitur SaaS & Role Karyawan)
    |--------------------------------------------------------------------------
    */

   public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        return $user->hasPermission('products.multi_uom');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermission('products.multi_uom');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermission('products.multi_uom');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasPermission('products.multi_uom');
    }
    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return UomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUoms::route('/'),
            'create' => CreateUom::route('/create'),
            'edit' => EditUom::route('/{record}/edit'),
        ];
    }
}