<?php

namespace App\Filament\Tenant\Resources\GiftCards\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;

class GiftCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kartu & Saldo')
                    ->schema([
                        TextInput::make('card_number')
                            ->label('Nomor Kartu / Barcode')
                            ->placeholder('Cth: GC-88912388')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', Filament::getTenant()->id);
                            })
                            ->default(fn () => 'GC-' . strtoupper(str()->random(8))),

                        Select::make('customer_id')
                            ->label('Pemegang Kartu (Opsional)')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih pelanggan jika kartu ini bersifat personal'),

                        TextInput::make('balance')
                            ->label('Saldo Awal')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(0),

                        DatePicker::make('expiry_date')
                            ->label('Tanggal Kedaluwarsa')
                            ->native(false)
                            ->placeholder('Pilih tanggal jika ada'),

                        Toggle::make('is_active')
                            ->label('Kartu Aktif & Dapat Digunakan')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
            ]);
    }
}