<?php

namespace App\Filament\Tenant\Resources\Expenses;

use App\Filament\Tenant\Resources\Expenses\Pages;
use App\Models\Transaction; 
use App\Models\Account; // <-- Wajib import model Account
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;

// Actions untuk Filament 4
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;

class ExpenseResource extends Resource
{
    // ... (Properti resource, model, navigationGroup, dan form() dibiarkan SAMA PERSIS seperti milik Anda) ...
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Pengeluaran (Expense)';
    protected static ?string $pluralModelLabel = 'Data Pengeluaran';
    protected static ?string $slug = 'expenses'; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-down';
    protected static ?string $navigationLabel = 'Pengeluaran (Expense)';
    protected static string | \UnitEnum | null $navigationGroup = 'Transaksi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Pengeluaran Kas')
                    ->schema([
                        Forms\Components\Hidden::make('type')->default('expense'),
                        Forms\Components\Hidden::make('status')->default('completed'),
                        Forms\Components\Hidden::make('in_out')->default('out'),
                        Forms\Components\Hidden::make('payment_method')->default('cash'),

                        Forms\Components\Select::make('outlet_id')
                            ->label('Outlet / Cabang')
                            ->relationship('outlet', 'name')
                            ->default(fn () => auth()->user()?->outlet_id) 
                            ->required(),

                        Forms\Components\TextInput::make('notes') 
                            ->label('Keterangan Pengeluaran')
                            ->placeholder('Contoh: Beli sabun lantai / Bayar Listrik / Gaji Harian')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('account_id')
                            ->label('Sumber Dana (Rekening/Kas)')
                            ->relationship('account', 'name', function (Builder $query) {
                                return $query->where('is_active', true);
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
                    ->searchable(),

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
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Filter Outlet'),
                
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'name')
                    ->label('Filter Sumber Dana'),
            ])
            // PERBAIKAN: Tambahkan DeleteAction dan logika Revert Saldo
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
                Tables\Actions\BulkActionGroup::make([
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

        if ($user && $user->isOwner()) {
            return $query;
        }

        return $query->where('outlet_id', $user?->outlet_id);
    }
}