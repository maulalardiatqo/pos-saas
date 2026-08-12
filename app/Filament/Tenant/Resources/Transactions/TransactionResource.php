<?php

namespace App\Filament\Tenant\Resources\Transactions;

use App\Filament\Tenant\Resources\Transactions\Pages;
use App\Models\Transaction;
use App\Models\StockMovement;
use App\Models\Stock; // <-- IMPORT MODEL STOCK
use App\Models\Account; 
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Forms;
use Filament\Schemas\Schema;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\RawJs;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';
    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';
    
    protected static ?string $navigationLabel = 'Riwayat POS';
    protected static ?string $pluralLabel = 'Riwayat Transaksi POS';

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->default('Umum')
                    ->searchable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total Akhir')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Kasir')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Selesai',
                        'cancelled' => 'VOID (Batal)',
                        default => strtoupper($state),
                    }),
            ])
            ->filters([
                SelectFilter::make('outlet_id')
                    ->label('Outlet')
                    ->relationship('outlet', 'name') 
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->isOwner() || auth()->user()?->isPlatform()),

                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan (VOID)',
                    ]),
               Filter::make('created_at')
                    ->label('Rentang Waktu')
                    ->schema([                   
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')
                                    ->label('Dari Tanggal')
                                    ->placeholder('Pilih tanggal mulai'),
                                DatePicker::make('created_until')
                                    ->label('Sampai Tanggal')
                                    ->placeholder('Pilih tanggal akhir'),
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                ViewAction::make()->label('Detail'),
                
                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Void Transaksi POS')
                    ->modalDescription('Apakah Anda yakin ingin membatalkan transaksi ini? Stok barang, Poin, dan Saldo Rekening akan ditarik kembali secara otomatis.')
                    ->hidden(fn (Transaction $record) => $record->status === 'cancelled')
                    ->action(function (Transaction $record) {
                        DB::transaction(function () use ($record) {
                            $companyId = filament()->getTenant()->id;
                            $outletId = $record->outlet_id;

                            // 1. Ubah status nota menjadi voided (cancelled)
                            $record->update(['status' => 'cancelled']);

                            // 2. KEMBALIKAN STOK (MENGGUNAKAN TABEL STOCKS & LOCKING)
                            foreach ($record->items as $item) {
                                $product = $item->product;
                                if (!$product) continue;

                                $isService = ($product->item_type === 'service');
                                $isBundle = in_array($product->product_type, ['bundle', 'recipe']);

                                if ($isBundle) {
                                    $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                                    foreach ($components as $comp) {
                                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                                        
                                        if ($child && $child->item_type === 'goods') {
                                            $qtyToReturn = (float) $item->base_qty * (float) $comp->quantity;
                                            
                                            // Kunci & Kembalikan Stok Komponen
                                            $stockRecord = Stock::firstOrCreate(
                                                ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $comp->child_product_id],
                                                ['qty' => 0]
                                            );
                                            $stockRecord->lockForUpdate();

                                            $balanceBefore = (float) $stockRecord->qty;
                                            $balanceAfter = $balanceBefore + $qtyToReturn;

                                            $stockRecord->update(['qty' => $balanceAfter]);

                                            StockMovement::create([
                                                'company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $comp->child_product_id,
                                                'type' => 'void', 'reference_type' => Transaction::class, 'reference_id' => $record->id,
                                                'quantity' => $qtyToReturn, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                                                'remarks' => 'VOID Nota POS (Paket/Bundle): ' . $record->transaction_number,
                                            ]);
                                        }
                                    }
                                } elseif (!$isService) {
                                    $qtyToReturn = (float) $item->base_qty;

                                    // Kunci & Kembalikan Stok Barang Biasa
                                    $stockRecord = Stock::firstOrCreate(
                                        ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id],
                                        ['qty' => 0]
                                    );
                                    $stockRecord->lockForUpdate();

                                    $balanceBefore = (float) $stockRecord->qty;
                                    $balanceAfter = $balanceBefore + $qtyToReturn;

                                    $stockRecord->update(['qty' => $balanceAfter]);

                                    StockMovement::create([
                                        'company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id,
                                        'type' => 'void', 'reference_type' => Transaction::class, 'reference_id' => $record->id,
                                        'quantity' => $qtyToReturn, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                                        'remarks' => 'VOID Nota POS: ' . $record->transaction_number,
                                    ]);
                                }
                            }

                            // 3. Tarik poin dari pelanggan
                            if ($record->customer_id) {
                                // (Sesuaikan nilai pembagi poin dengan setting loyalty Anda)
                                $earnedPoints = floor($record->grand_total / 10000); 
                                if ($earnedPoints > 0) {
                                    \App\Models\PointHistory::create([
                                        'company_id' => $companyId,
                                        'customer_id' => $record->customer_id,
                                        'type' => 'redeem', 
                                        'amount' => $earnedPoints,
                                        'reference_id' => $record->transaction_number,
                                        'description' => 'Penarikan poin otomatis (VOID)',
                                    ]);
                                    \App\Models\Customer::where('id', $record->customer_id)->decrement('points_balance', $earnedPoints);
                                }
                            }
                            
                            // 4. Kurangi omset kasir (Pos Session)
                            if ($record->pos_session_id) {
                                $session = \App\Models\PosSession::find($record->pos_session_id);
                                if ($session && $session->status === 'open') {
                                    $session->decrement('total_sales', $record->grand_total);
                                    
                                    if ($record->payment_method === 'cash') {
                                        $session->decrement('total_cash_sales', $record->grand_total);
                                    }
                                }
                            }

                            // ==========================================================
                            // 5. TARIK KEMBALI SALDO DARI REKENING (ACCOUNT)
                            // ==========================================================
                            if ($record->account_id) {
                                $account = \App\Models\Account::find($record->account_id);
                                if ($account) {
                                    $netAmount = $record->grand_total - (float) $record->admin_fee;
                                    $account->decrement('balance', $netAmount);
                                }
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Transaksi berhasil di-Void!')
                            ->body('Stok barang, poin pelanggan, dan Saldo Kas telah dikembalikan.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Transaksi')
                    ->schema([
                        TextInput::make('transaction_number')->label('No. Nota'),
                        DateTimePicker::make('created_at')->label('Tanggal'),
                        TextInput::make('status'),
                        TextInput::make('payment_method')->label('Pembayaran'),
                        Select::make('outlet_id')
                            ->relationship('outlet', 'name')
                            ->label('Outlet / Cabang'),
                            
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Kasir / Dibuat Oleh'),
                        TextInput::make('points_used')->label('Penggunaan Point'),

                    ])
                    ->columns(4)
                    ->disabled(),
                Section::make('Ringkasan Pembayaran')
                    ->schema([
                        TextInput::make('subtotal')->label('Total Kotor')->prefix('Rp ')->mask(RawJs::make('$money($input, \',\', \'.\', 0)')),
                        TextInput::make('discount')->label('Total Diskon')->prefix('Rp ')->mask(RawJs::make('$money($input, \',\', \'.\', 0)')),
                        TextInput::make('point_discount_amount')->label('Discount Point')->prefix('Rp ')->mask(RawJs::make('$money($input, \',\', \'.\', 0)')),
                        TextInput::make('tax')->label('Total Pajak')->prefix('Rp ')->mask(RawJs::make('$money($input, \',\', \'.\', 0)')),
                        TextInput::make('grand_total')->label('Total Akhir')->prefix('Rp ')->mask(RawJs::make('$money($input, \',\', \'.\', 0)')),
                        
                    ])
                    ->columns(4)
                    ->disabled(),
                Section::make('Rincian Belanja')
                    ->extraAttributes(['style' => 'overflow-x: auto; min-width: 100%;']) 
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Produk')
                                    ->columnSpan(3),
                                    
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->columnSpan(1), 
                                    
                                Select::make('uom_id')
                                    ->relationship('uom', 'name')
                                    ->label('Satuan')
                                    ->columnSpan(2),
                                    
                                TextInput::make('selling_price')
                                    ->label('Harga')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->columnSpan(2),
                                    
                                TextInput::make('discount_rate')
                                    ->label('Disc (%)')
                                    ->suffix('%')
                                    ->numeric()
                                    ->columnSpan(2), 
                                    
                                TextInput::make('discount_amount')
                                    ->label('Disc (Rp)')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->columnSpan(2),
                                    
                                TextInput::make('tax_rate')
                                    ->label('Pajak (%)')
                                    ->suffix('%')
                                    ->numeric()
                                    ->columnSpan(2), 
                                    
                                TextInput::make('tax_amount')
                                    ->label('Pajak (Rp)')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->columnSpan(2),

                                TextInput::make('subtotal')
                                    ->label('Total (Rp)')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->columnSpan(2),
                            ])
                            ->columns(18) 
                            ->extraAttributes(['style' => 'min-width: 1300px;']) 
                            ->deletable(false)
                            ->addable(false)
                    ]),
                    
                
            ])
            ->columns(1); 
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
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}