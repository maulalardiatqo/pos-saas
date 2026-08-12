<?php

namespace App\Filament\Tenant\Resources\OpeningBalances;

use App\Filament\Tenant\Resources\OpeningBalances\Pages;
use App\Models\Transaction;

// 1. FILAMENT 4 SCHEMAS & LAYOUTS
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

// 2. FILAMENT 4 FORM INPUTS
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

// 3. CORE & TABLES
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;

// 4. ACTIONS
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class OpeningBalanceResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Opening Balance';
    protected static ?string $modelLabel = 'Saldo Awal';
    protected static ?string $pluralModelLabel = 'Saldo Awal';
    protected static string | \UnitEnum | null $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'opening-balance';

    // HANYA OWNER YANG BISA MENGAKSES
    public static function canViewAny(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('type')->default('opening_balance'),
                Hidden::make('in_out')->default('in'),
                Hidden::make('status')->default('completed'),
                Hidden::make('payment_method')->default('cash'),

                Section::make('Input Saldo Awal (Opening Balance)')
                    ->description('Masukkan saldo uang tunai atau saldo rekening bank bawaan Anda.')
                    ->schema([
                        TextInput::make('transaction_number')
                            ->label('Nomor Referensi')
                            ->default('OB-' . date('Ymd-His'))
                            ->readOnly()
                            ->required()
                            ->extraAttributes(['class' => 'font-mono font-bold text-gray-500']),

                        DateTimePicker::make('created_at')
                            ->label('Tanggal Saldo')
                            ->default(now())
                            ->required(),

                        Select::make('outlet_id')
                            ->relationship('outlet', 'name')
                            ->label('Untuk Cabang / Outlet')
                            ->default(fn () => auth()->user()?->outlet_id)
                            ->searchable()
                            ->preload() // preload aman di sini karena list outlet tidak bergantung field lain
                            ->live() // Memicu update form saat diubah
                            ->afterStateUpdated(fn (Set $set) => $set('account_id', null)) 
                            ->required(),

                        Select::make('account_id')
                            ->relationship(
                                name: 'account', 
                                titleAttribute: 'name', 
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $outletId = $get('outlet_id');
                                    
                                    // Jika outlet belum dipilih, jangan tampilkan akun apapun
                                    if (!$outletId) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    // Tampilkan akun yang aktif, dan bersifat Global (null) ATAU milik cabang tersebut
                                    return $query->where('is_active', true)
                                        ->where(function ($q) use ($outletId) {
                                            $q->whereNull('outlet_id')
                                              ->orWhere('outlet_id', $outletId);
                                        });
                                }
                            )
                            ->label('Simpan ke Rekening / Kas')
                            ->searchable()
                            // ->preload() // <-- INI WAJIB DIHAPUS agar query dinamisnya dieksekusi via AJAX!
                            ->required(),

                        TextInput::make('amount_paid')
                            ->label('Nominal Saldo')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->required()
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                            ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('Nomor Ref')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Rekening / Kas')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Nominal Saldo')
                    ->money('IDR', locale: 'id')
                    ->color('success')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'name')
                    ->label('Filter Rekening'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'opening_balance');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOpeningBalances::route('/'),
            'create' => Pages\CreateOpeningBalance::route('/create'),
            'edit'   => Pages\EditOpeningBalance::route('/{record}/edit'),
        ];
    }
}