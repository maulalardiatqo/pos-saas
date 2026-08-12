<?php

namespace App\Filament\Tenant\Resources\Reports\FinanceReports;

use App\Filament\Tenant\Resources\Reports\FinanceReports\Pages;
use App\Filament\Tenant\Resources\Reports\FinanceReports\Widgets\FinanceStatsWidget; 
use App\Models\Transaction; 
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model; // <-- Tambahan untuk type-hinting canEdit & canDelete
use App\Filament\Exports\TransactionExporter;
use Filament\Actions\ExportAction;

class FinanceReportResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Laporan Keuangan';
    protected static ?string $pluralModelLabel = 'Laporan Keuangan';
    protected static ?string $slug = 'reports/finance'; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Lap. Keuangan';
    protected static string | \UnitEnum | null $navigationGroup = 'Laporan (Reports)';
    protected static ?int $navigationSort = 1;

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (Role-Based Access Control)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user->isOwner() || $user->hasPermission('reports.finance');
    }

    public static function canCreate(): bool
    {
        return false; // Laporan tidak bisa dibuat manual dari sini
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Laporan keuangan absolut, tidak boleh diedit dari menu laporan
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Laporan tidak boleh dihapus dari sini (harus via void di menu asalnya)
    }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Form & Tabel
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('transaction_number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('outlet.name')->searchable()->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis Dokumen')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sale'            => 'Penjualan',
                        'revenue'         => 'Pemasukan',
                        'expense'         => 'Pengeluaran',
                        'cashin'          => 'Kas Masuk',
                        'cashout'         => 'Kas Keluar',
                        'refund'          => 'Pengembalian',
                        'purchaseorder'   => 'PO (Pembelian)',
                        'purchaserequest' => 'Request PO',
                        'goodreceive'     => 'Penerimaan Barang',
                        'invoice'         => 'Invoice / Tagihan',
                        'asset_purchase'  => 'Pembelian Aset',
                        'opening_balance' => 'Saldo Awal',
                        default           => strtoupper($state),
                    })
                    ->color(fn ($record): string => $record->in_out === 'out' ? 'danger' : ($record->type === 'opening_balance' ? 'info' : 'success')),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Nominal Transaksi')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(function ($record) {
                        $isOut = $record->in_out === 'out';
                        $prefix = $isOut ? '- Rp ' : '+ Rp ';
                        return $prefix . number_format((float)$record->grand_total, 0, ',', '.');
                    })
                    ->color(fn ($record) => $record->in_out === 'out' ? 'danger' : 'success')
                    ->weight('bold'),

                // KOLOM BARU: MENAMPILKAN POTONGAN MIDTRANS
                Tables\Columns\TextColumn::make('admin_fee')
                    ->label('Biaya Admin (MDR)')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn ($state) => $state > 0 ? '- Rp ' . number_format((float)$state, 0, ',', '.') : '-')
                    ->color('danger')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('Dari'),
                        Forms\Components\DatePicker::make('created_until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name'),

                Tables\Filters\SelectFilter::make('in_out')
                    ->label('Arus Kas')
                    ->options([
                        'in'  => 'Kas Masuk (IN)',
                        'out' => 'Kas Keluar (OUT)',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'opening_balance' => 'Saldo Awal (Modal)', 
                        'sale'            => 'Penjualan',
                        'revenue'         => 'Pemasukan Tambahan',
                        'expense'         => 'Pengeluaran',
                        'asset_purchase'  => 'Beli Aset',
                        'purchaseorder'   => 'PO (Pembelian Barang)',
                    ]),
            ])
            ->headerActions([\Filament\Actions\ExportAction::make()->exporter(TransactionExporter::class)])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('status', 'completed')
            ->whereNotNull('in_out')
            ->whereNull('deleted_at'); 
        
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user && $user->isOwner()) {
            return $query;
        }

        return $query->where('outlet_id', $user?->outlet_id);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceReports::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            FinanceStatsWidget::class,
        ];
    }
}