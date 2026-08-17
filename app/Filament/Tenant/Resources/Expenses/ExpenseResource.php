<?php

namespace App\Filament\Tenant\Resources\Expenses;

use App\Filament\Tenant\Resources\Expenses\Pages;
use App\Models\Transaction; 
use App\Models\Account; 
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;

// Actions
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class ExpenseResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Pengeluaran (Expense)';
    protected static ?string $pluralModelLabel = 'Data Pengeluaran';
    protected static ?string $slug = 'expenses'; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-down';
    protected static ?string $navigationLabel = 'Pengeluaran (Expense)';
    protected static string | \UnitEnum | null $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $schema
            ->components([
                Section::make('Form Pengeluaran Kas')
                    ->schema([
                        Forms\Components\Hidden::make('type')->default('expense'),
                        Forms\Components\Hidden::make('status')->default('completed'),
                        Forms\Components\Hidden::make('in_out')->default('out'),
                        Forms\Components\Hidden::make('payment_method')->default('cash'),

                        // INPUT OUTLET: Dropdown jika Owner/Platform, Hidden jika Staf biasa
                        Forms\Components\Select::make('outlet_id')
                            ->label('Outlet / Cabang')
                            ->relationship('outlet', 'name')
                            ->default(fn () => $user?->outlet_id) 
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible($isOwnerOrPlatform),

                        Forms\Components\Hidden::make('outlet_id')
                            ->default(fn () => $user?->outlet_id)
                            ->visible(!$isOwnerOrPlatform),

                        Forms\Components\TextInput::make('notes') 
                            ->label('Keterangan Pengeluaran')
                            ->placeholder('Contoh: Beli sabun lantai / Bayar Listrik / Gaji Harian')
                            ->required()
                            ->maxLength(255),

                        // INPUT SUMBER DANA (ACCOUNT): Hanya tampilkan akun sesuai outlet user jika bukan owner
                        Forms\Components\Select::make('account_id')
                            ->label('Sumber Dana (Rekening/Kas)')
                            ->relationship('account', 'name', function (Builder $query) use ($user, $isOwnerOrPlatform) {
                                $query->where('is_active', true);

                                // Jika bukan Owner/Platform, filter hanya akun milik outlet user ATAU akun Global
                                if (!$isOwnerOrPlatform) {
                                    $query->where(function ($q) use ($user) {
                                        $q->whereNull('outlet_id')
                                          ->orWhere('outlet_id', $user?->outlet_id);
                                    });
                                }

                                return $query;
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('grand_total')
                            ->label('Total Pengeluaran')
                            ->required()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\')'))
                            ->stripCharacters('.')
                            ->numeric(),
                            
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Waktu Transaksi')
                            ->default(now())
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('No. Referensi')
                    ->searchable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->visible($isOwnerOrPlatform),

                Tables\Columns\TextColumn::make('notes') 
                    ->label('Keterangan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Sumber Dana')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.'))
                    ->alignment(Alignment::End),
            ])
            ->filters([
                // Filter Outlet hanya untuk Owner/Platform
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Filter Outlet')
                    ->visible($isOwnerOrPlatform),
                
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'name', function (Builder $query) use ($user, $isOwnerOrPlatform) {
                        if (!$isOwnerOrPlatform) {
                            $query->where(function ($q) use ($user) {
                                $q->whereNull('outlet_id')
                                  ->orWhere('outlet_id', $user?->outlet_id);
                            });
                        }
                        return $query;
                    })
                    ->label('Filter Sumber Dana'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Transaction $record) {
                        if ($record->account_id) {
                            // Uang batal keluar -> kembalikan ke rekening (increment)
                            Account::find($record->account_id)?->increment('balance', $record->grand_total);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if ($record->account_id) {
                                    // Uang batal keluar -> kembalikan ke rekening (increment)
                                    Account::find($record->account_id)?->increment('balance', $record->grand_total);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('type', 'expense');
        
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && ($user->isOwner() || $user->isPlatform())) {
            return $query;
        }

        return $query->where('outlet_id', $user?->outlet_id);
    }
}