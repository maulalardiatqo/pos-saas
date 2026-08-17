<?php

namespace App\Filament\Tenant\Resources\SalesInvoices;

use App\Filament\Tenant\Resources\SalesInvoices\Pages;
use App\Filament\Tenant\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Tenant\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $slug = 'sales-invoices';
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 7;
    
    protected static ?string $navigationLabel = 'Penjualan Tempo';
    protected static ?string $pluralLabel = 'Daftar Invoice (Tempo)';

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; } 

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'invoice');
    }

    public static function form(Schema $schema): Schema
    {
        return SalesInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'view'   => Pages\ViewSalesInvoice::route('/{record}'),
        ];
    }
}