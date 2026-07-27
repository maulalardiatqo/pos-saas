<?php

namespace App\Filament\Tenant\Resources\Revenues;

use App\Filament\Tenant\Resources\Revenues\Pages;
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
use Filament\Forms\Components\DateTimePicker;

// Gunakan namespace Tables\Actions yang tepat untuk Filament 4
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class RevenueResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Pemasukan (Revenue)';
    protected static ?string $pluralModelLabel = 'Data Pemasukan';
    protected static ?string $slug = 'revenues'; 

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-up'; 
    protected static ?string $navigationLabel = 'Pemasukan (Revenue)';
    protected static string | \UnitEnum | null $navigationGroup = 'Transaksi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Pemasukan Kas')
                    ->schema([
                        Forms\Components\Hidden::make('type')->default('revenue'),
                        Forms\Components\Hidden::make('status')->default('completed'),
                        Forms\Components\Hidden::make('in_out')->default('in'),
                        Forms\Components\Hidden::make('payment_method')->default('cash'),

                        Forms\Components\Select::make('outlet_id')
                            ->label('Outlet / Cabang')
                            ->relationship('outlet', 'name')
                            ->default(fn () => auth()->user()?->outlet_id)
                            ->required(),

                        Forms\Components\TextInput::make('notes') 
                            ->label('Keterangan Pemasukan')
                            ->placeholder('Contoh: Tambahan modal / Pendapatan parkir')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('account_id')
                            ->label('Disimpan Ke (Rekening/Kas)')
                            ->relationship('account', 'name', function (Builder $query) {
                                return $query->where('is_active', true);
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('grand_total')
                            ->label('Total Pemasukan')
                            ->required()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\')'))
                            ->stripCharacters('.')
                            ->numeric(),
                            
                        DateTimePicker::make('created_at')
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
                    ->label('Disimpan Ke')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.'))
                    ->alignment(Alignment::End)
                    ->color('success') 
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Filter Outlet'),
                    
                Tables\Filters\SelectFilter::make('account_id')
                    ->relationship('account', 'name')
                    ->label('Filter Rekening'),
            ])
            ->actions([
                EditAction::make(),
                // PERBAIKAN: Tarik saldo jika dihapus dari tabel
                DeleteAction::make()
                    ->before(function (Transaction $record) {
                        if ($record->account_id) {
                            // Uang batal masuk -> kurangi saldo (decrement)
                            Account::find($record->account_id)?->decrement('balance', $record->grand_total);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if ($record->account_id) {
                                    // Uang batal masuk -> kurangi saldo (decrement)
                                    Account::find($record->account_id)?->decrement('balance', $record->grand_total);
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
            'index'  => Pages\ListRevenues::route('/'),
            'create' => Pages\CreateRevenue::route('/create'),
            'edit'   => Pages\EditRevenue::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('type', 'revenue');
        
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && $user->isOwner()) {
            return $query;
        }

        return $query->where('outlet_id', $user?->outlet_id);
    }
}