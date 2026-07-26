<?php

namespace App\Filament\Tenant\Resources\Brands\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Brand')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Brand')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('company_id', Filament::getTenant()->id);
                            }),
                        
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3),
                        
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])
            ]);
    }
}