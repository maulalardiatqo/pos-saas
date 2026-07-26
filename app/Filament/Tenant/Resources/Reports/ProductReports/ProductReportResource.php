<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports;

use App\Filament\Tenant\Resources\Reports\ProductReports\Pages\ListProductReports;
use App\Filament\Tenant\Resources\Reports\ProductReports\Schemas\ProductReportForm;
use App\Filament\Tenant\Resources\Reports\ProductReports\Schemas\ProductReportInfolist;
use App\Filament\Tenant\Resources\Reports\ProductReports\Tables\ProductReportsTable;
use App\Models\Product; // <-- KITA MENGGUNAKAN MODEL PRODUCT
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductReportResource extends Resource
{
    // 1. Arahkan model ke Product dan sesuaikan slug URL-nya
    protected static ?string $model = Product::class;
    protected static ?string $slug = 'reports/products';

    // 2. Konfigurasi Tampilan Menu Sidebar
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube; 
    protected static string | \UnitEnum | null $navigationGroup = 'Laporan (Reports)';
    protected static ?string $navigationLabel = 'Lap. Produk';
    protected static ?string $pluralLabel = 'Lap. Produk';
    protected static ?int $navigationSort = 3; 

    // 3. KUNCI HAK AKSES (READ-ONLY)
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return ProductReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            // HANYA SISAKAN INDEX SAJA (Karena Read-Only)
            'index' => ListProductReports::route('/'),
        ];
    }
}