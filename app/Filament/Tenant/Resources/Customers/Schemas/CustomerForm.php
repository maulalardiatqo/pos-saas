<?php

namespace App\Filament\Tenant\Resources\Customers\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | Informasi Pelanggan
                |--------------------------------------------------------------------------
                */
                Section::make('Informasi Pelanggan')
                    ->description('Data identitas dan kontak pelanggan.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Pelanggan')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule) => $rule->where(
                                    'company_id',
                                    Filament::getTenant()->id
                                )
                            )
                            ->default(fn () => 'CUST-' . strtoupper(str()->random(5))),

                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(150),

                        Select::make('outlet_id')
                            ->label('Pilih Outlet / Cabang')
                            ->relationship('outlet', 'name', function (Builder $query) use ($isOwnerOrPlatform, $user) {
                                if (!$isOwnerOrPlatform) {
                                    $query->where('id', $user->outlet_id);
                                }
                                return $query;
                            })
                            ->placeholder('Pelanggan Umum (Semua Outlet)')
                            ->helperText('Kosongkan jika pelanggan ini bisa berbelanja di semua cabang (Global).')
                            ->searchable()
                            ->preload(),

                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),

                        Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | FITUR KHUSUS BENGKEL: Daftar Kendaraan
                |--------------------------------------------------------------------------
                */
                Section::make('Daftar Kendaraan (Motor)')
                    ->description('Masukkan data kendaraan milik pelanggan ini (bisa lebih dari satu).')
                    // =================================================================
                    // LOGIKA PENYEMBUNYIAN (Hanya tampil jika kode plan bengkel_motor)
                    // =================================================================
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan, 'code') === 'bengkel_motor')
                    ->schema([
                        Repeater::make('vehicles')
                            ->relationship('vehicles')
                            ->label('')
                            ->addActionLabel('Tambah Kendaraan')
                            // Menyuntikkan company_id otomatis ke tabel vehicles saat disimpan
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['company_id'] = Filament::getTenant()->id;
                                return $data;
                            })
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    Select::make('jenis')
                                        ->label('Jenis Motor')
                                        ->options([
                                            'matic'            => 'Matic',
                                            'bebek'            => 'Bebek',
                                            'sport'            => 'Sport',
                                            'adventure'        => 'Adventure',
                                            'motor elektronik' => 'Motor Elektronik',
                                        ])
                                        ->required(),

                                    TextInput::make('type')
                                        ->label('Tipe / Model')
                                        ->placeholder('Contoh: Honda Beat FI')
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('nomor_plat')
                                        ->label('Nomor Plat')
                                        ->placeholder('Contoh: D 1234 ABC')
                                        ->required()
                                        ->maxLength(50)
                                        ->extraAttributes(['style' => 'text-transform: uppercase;'])
                                        ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '', (string) $state))),
                                ])
                            ])
                            ->defaultItems(0) // Default kosong agar tidak mengganggu jika belum ada motor
                    ]),

                /*
                |--------------------------------------------------------------------------
                | CRM + Status
                |--------------------------------------------------------------------------
                */
                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->schema([
                        Section::make('CRM & Loyalty')
                            ->visible(fn () => data_get(
                                Filament::getTenant()?->subscriptionPlan?->features,
                                'crm.membership'
                            ) === true)
                            ->schema([
                                Select::make('membership_id')
                                    ->label('Membership')
                                    ->relationship('membership', 'name')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('points_balance')
                                    ->label('Saldo Poin')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Poin bertambah otomatis dari transaksi.'),
                            ]),

                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Pelanggan Aktif')
                                    ->default(true),
                            ]),
                    ]),

            ]);
    }
}