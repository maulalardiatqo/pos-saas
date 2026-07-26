<?php

namespace App\Filament\Tenant\Resources\Uoms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;

class UomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Satuan')
                    ->description('Kelola satuan ukur/jual untuk produk Anda.')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Satuan')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', Filament::getTenant()->id);
                            })
                            ->helperText('Contoh: PCS, KG, LUSIN'),

                        TextInput::make('name')
                            ->label('Nama Satuan')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Contoh: Pieces, Kilogram, Lusin'),

                        TextInput::make('symbol')
                            ->label('Simbol (Opsional)')
                            ->maxLength(20)
                            ->helperText('Contoh: pcs, kg, dz'),

                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}