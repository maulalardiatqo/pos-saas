<?php

namespace App\Filament\Tenant\Resources\PurchaseReturns;

use App\Filament\Tenant\Resources\PurchaseReturns\Pages;
use App\Filament\Tenant\Resources\PurchaseReturns\Schemas\PurchaseReturnForm;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\ViewAction;
use UnitEnum;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $slug = 'purchase-returns';
     protected static string | \BackedEnum | null $navigationIcon ='heroicon-o-arrow-path-rounded-square';
    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';
    
    protected static ?string $navigationLabel = 'Retur Pembelian';
    protected static ?string $pluralLabel = 'Daftar Retur Pembelian';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'refund')
            ->where('in_out', 'in');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')->label('No. Retur')->searchable()->weight('bold'),
                TextColumn::make('referenceTransaction.transaction_number')->label('Ref. Nota PO')->searchable()->color('gray'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y'),
                TextColumn::make('supplier.name')->label('Pemasok')->searchable(),
                TextColumn::make('grand_total')->label('Total Refund')->money('IDR', locale: 'id')->color('success')->weight('bold'),
            ])
            // TAMBAHKAN INI:
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

// Di dalam fungsi getPages():
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view'   => Pages\ViewPurchaseReturn::route('/{record}'),
        ];
    }
}