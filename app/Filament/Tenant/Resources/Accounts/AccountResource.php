<?php

namespace App\Filament\Tenant\Resources\Accounts;

use App\Filament\Tenant\Resources\Accounts\Pages;
use App\Models\Account;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model; // <-- Tambahan untuk type-hinting hak akses
use BackedEnum;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;

use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Akun Keuangan';
    protected static ?string $modelLabel = 'Akun Keuangan';
    protected static ?string $pluralModelLabel = 'Akun Keuangan';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 1;

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (Role-Based Access Control)
    |--------------------------------------------------------------------------
    | Mengatur agar menu ini hanya muncul dan bisa diakses oleh Owner, 
    | atau jabatan yang secara eksplisit diberi centang "Kelola Rekening"
    */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Hanya muncul untuk Owner, atau karyawan yang punya hak akses 'finance.account'
        return $user->isOwner() || $user->hasPermission('finance.account');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Form & Tabel
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Akun / Rekening')
                                ->placeholder('Contoh: Bank BCA, Laci Kasir 2, OVO')
                                ->required()
                                ->maxLength(255),
                                
                            TextInput::make('account_number')
                                ->label('Nomor Rekening (Opsional)')
                                ->placeholder('Contoh: 1234567890')
                                ->maxLength(255),
                                
                            Select::make('outlet_id')
                                ->label('Lokasi Outlet')
                                ->relationship('outlet', 'name')
                                ->helperText('Kosongkan jika rekening ini digunakan untuk seluruh cabang.')
                                ->preload()
                                ->searchable(),
                                
                            TextInput::make('balance')
                                ->label('Saldo Awal')
                                ->numeric()
                                ->default(0)
                                ->prefix('Rp')
                                ->hiddenOn('edit'),
                        ]),
                    ]),

                Section::make('Metode Pembayaran & Status')
                    ->description('Pilih metode pembayaran apa saja yang uangnya akan masuk ke rekening ini.')
                    ->schema([
                        CheckboxList::make('payment_methods')
                            ->label('Metode yang Didukung')
                            ->options([
                                'cash' => 'Tunai (Cash)',
                                'qris' => 'QRIS',
                                'transfer' => 'Transfer Bank',
                                'credit_card' => 'Kartu Kredit',
                                'debit_card' => 'Kartu Debit',
                                'ewallet' => 'E-Wallet (Gopay/OVO/ShopeePay)',
                            ])
                            ->required()
                            ->columns(3)
                            ->gridDirection('row'),
                            
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Matikan jika rekening ini sudah tidak digunakan lagi.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->badge()
                    ->color('info')
                    ->default('Pusat / Semua Cabang'),
                    
                Tables\Columns\TextColumn::make('payment_methods')
                    ->label('Metode')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(function (array|string $state): string {
                        $methods = is_string($state) ? json_decode($state, true) : $state;
                        
                        if (!is_array($methods)) return '-';

                        $labels = [
                            'cash' => 'Tunai',
                            'qris' => 'QRIS',
                            'transfer' => 'Transfer',
                            'credit_card' => 'Kartu Kredit',
                            'debit_card' => 'Debit',
                            'ewallet' => 'E-Wallet',
                        ];

                        $formatted = array_map(fn($method) => $labels[$method] ?? $method, $methods);
                        return implode(', ', $formatted);
                    }),
                    
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo Saat Ini')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->label('Filter Outlet')
                    ->relationship('outlet', 'name'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}