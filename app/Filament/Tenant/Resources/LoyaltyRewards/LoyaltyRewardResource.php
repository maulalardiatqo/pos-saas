<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards;

use App\Models\LoyaltyReward;
use App\Filament\Tenant\Resources\LoyaltyRewardResource\Pages;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;

use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

use App\Filament\Tenant\Resources\LoyaltyRewards\Pages\ListLoyaltyRewards;
use App\Filament\Tenant\Resources\LoyaltyRewards\Pages\CreateLoyaltyReward;
use App\Filament\Tenant\Resources\LoyaltyRewards\Pages\EditLoyaltyReward;
class LoyaltyRewardResource extends Resource
{
    protected static ?string $model = LoyaltyReward::class;
    protected static ?string $slug = 'crm/loyalty-rewards';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-gift';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM & Pelanggan';
    protected static ?string $navigationLabel = 'Katalog Hadiah';
    protected static ?string $pluralLabel = 'Katalog Hadiah (Reward)';

    // Validasi Izin Akses (Proteksi Modul)
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant || !$tenant->hasFeature('crm.point')) {
            return false;
        }

        $user = auth()->user();
        return $user && ($user->isOwner() || $user->isPlatform());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Hadiah')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Hadiah')
                                ->placeholder('Contoh: Gratis Ganti Oli / Voucher Diskon')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('points_required')
                                ->label('Syarat Poin')
                                ->numeric()
                                ->default(100)
                                ->suffix('Poin')
                                ->required(),

                            Select::make('reward_type')
                                ->label('Jenis Hadiah')
                                ->options([
                                    'product' => 'Berupa Barang / Jasa',
                                    'discount' => 'Berupa Voucher Diskon',
                                ])
                                ->default('product')
                                ->required()
                                ->live(), // <- Agar form dinamis (berubah saat diplih)

                            // Tampil jika pilih 'product'
                            Select::make('product_id')
                                ->label('Tautkan ke Master Produk/Jasa')
                                ->relationship('product', 'name', function (Builder $query) {
                                    return $query->where('company_id', Filament::getTenant()?->id);
                                })
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get) => $get('reward_type') === 'product')
                                ->visible(fn (Get $get) => $get('reward_type') === 'product'),

                            // Tampil jika pilih 'discount'
                            TextInput::make('discount_amount')
                                ->label('Nominal Diskon')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->numeric()
                                ->required(fn (Get $get) => $get('reward_type') === 'discount')
                                ->visible(fn (Get $get) => $get('reward_type') === 'discount'),
                        ]),
                    ]),

                Section::make('Media & Status')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Gambar / Ilustrasi Hadiah')
                            ->image()
                            ->directory('loyalty-rewards')
                            ->maxSize(2048),
                            
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Nama Hadiah')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('points_required')
                    ->label('Butuh Poin')
                    ->badge()
                    ->color('success')
                    ->suffix(' Pts')
                    ->sortable(),

                TextColumn::make('reward_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'product' ? 'Barang/Jasa' : 'Voucher Diskon')
                    ->color(fn ($state) => $state === 'product' ? 'info' : 'warning'),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyRewards::route('/'),
            'create' => CreateLoyaltyReward::route('/create'),
            'edit' => EditLoyaltyReward::route('/{record}/edit'),
        ];
    }
}