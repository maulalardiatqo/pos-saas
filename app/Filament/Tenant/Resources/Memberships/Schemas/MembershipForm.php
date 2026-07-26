<?php

namespace App\Filament\Tenant\Resources\Memberships\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Tingkat Keanggotaan')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Level (Tier)')
                            ->placeholder('Cth: Gold, VIP, Member')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', Filament::getTenant()->id);
                            })
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('min_points')
                                ->label('Syarat Minimal Poin')
                                ->numeric()
                                ->default(0)
                                ->helperText('Poin yang harus dicapai pelanggan untuk naik ke level ini.'),

                            TextInput::make('discount_percentage')
                                ->label('Diskon Otomatis')
                                ->numeric()
                                ->suffix('%')
                                ->default(0)
                                ->helperText('Kosongkan atau isi 0 jika tidak ada diskon khusus.'),
                        ]),
                        
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
            ]);
    }
}