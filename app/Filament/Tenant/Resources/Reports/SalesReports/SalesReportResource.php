<?php

namespace App\Filament\Tenant\Resources\Reports\SalesReports;

use App\Filament\Tenant\Resources\Reports\SalesReports\Pages\ListSalesReports;
use App\Filament\Tenant\Resources\Reports\SalesReports\Schemas\SalesReportForm;
use App\Filament\Tenant\Resources\Reports\SalesReports\Schemas\SalesReportInfolist;
use App\Filament\Tenant\Resources\Reports\SalesReports\Tables\SalesReportsTable;
use App\Models\Transaction; 
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesReportResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Laporan Penjualan';
    protected static ?string $pluralModelLabel = 'Laporan Penjualan';
    protected static ?string $slug = 'reports/sales'; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Lap. Penjualan';
    protected static string | \UnitEnum | null $navigationGroup = 'Laporan (Reports)';
    protected static ?int $navigationSort = 1;

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (Role-Based Access Control)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        // Hanya muncul untuk Owner, atau karyawan yang punya hak akses 'reports.sales'
        return $user->isOwner() || $user->hasPermission('reports.sales');
    }

    // KUNCI HAK AKSES (MURNI READ-ONLY)
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Form & Tabel
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return SalesReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where('type', 'sale') 
            ->where('status', 'completed');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user->isOwner() && !$user->isPlatform() && $user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReports::route('/'),
        ];
    }
}