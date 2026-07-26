<?php

namespace App\Filament\Tenant\Resources\Vouchers\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    
                    // KIRI: Aturan Main Voucher (Lebar 2/3)
                    Grid::make(1)->schema([
                        Section::make('Informasi & Potongan')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Nama Kampanye Promo')
                                        ->placeholder('Cth: Diskon Gajian')
                                        ->required()
                                        ->maxLength(150),

                                    TextInput::make('code')
                                        ->label('Kode Voucher')
                                        ->placeholder('Cth: GAJIANSERU')
                                        ->required()
                                        ->maxLength(50)
                                        ->alphaNum()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                            return $rule->where('company_id', Filament::getTenant()->id);
                                        }),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('discount_type')
                                        ->label('Tipe Potongan')
                                        ->options([
                                            'fixed' => 'Nominal Rupiah (Rp)',
                                            'percentage' => 'Persentase (%)',
                                        ])
                                        ->default('fixed')
                                        ->live()
                                        ->required(),

                                    TextInput::make('discount_value')
                                        ->label('Nilai Potongan')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->prefix(fn ($get) => $get('discount_type') === 'percentage' ? null : 'Rp')
                                        ->suffix(fn ($get) => $get('discount_type') === 'percentage' ? '%' : null),
                                ]),
                            ]),

                        Section::make('Batasan & Kuota')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('min_purchase')
                                        ->label('Minimal Pembelian')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0),

                                    TextInput::make('max_discount')
                                        ->label('Maksimal Diskon (Cap)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->helperText('Hanya berlaku untuk tipe persentase.')
                                        ->disabled(fn ($get) => $get('discount_type') === 'fixed'),
                                ]),

                                TextInput::make('usage_limit')
                                    ->label('Kuota Pemakaian')
                                    ->numeric()
                                    ->placeholder('Tak Terbatas')
                                    ->helperText('Kosongkan jika voucher bisa dipakai tanpa batas kuota.'),
                            ]),
                    ])->columnSpan(2),

                    Grid::make(1)->schema([
                        Section::make('Masa Berlaku')
                            ->schema([
                                DateTimePicker::make('start_date')
                                    ->label('Mulai Dari')
                                    ->native(false),

                                DateTimePicker::make('end_date')
                                    ->label('Sampai Dengan')
                                    ->native(false),
                            ]),

                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Aktifkan Voucher')
                                    ->default(true),
                            ]),
                    ])->columnSpan(1),

                ]),
            ]);
    }
}