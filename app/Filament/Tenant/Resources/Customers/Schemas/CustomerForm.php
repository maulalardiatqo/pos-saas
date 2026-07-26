<?php

namespace App\Filament\Tenant\Resources\Customers\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
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
                            ->rows(5)
                            ->columnSpanFull(),

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