<?php

namespace App\Filament\Tenant\Resources\Categories\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Kategori')
                    ->description('Kelompokkan produk Anda untuk memudahkan pencarian.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', Filament::getTenant()->id);
                            })
                            ->helperText('Contoh: Minuman, Makanan Ringan, dsb.')
                            ->columnSpanFull(),
                    ])
            ]);
    }
}