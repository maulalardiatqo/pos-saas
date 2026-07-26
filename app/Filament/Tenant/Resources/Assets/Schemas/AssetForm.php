<?php

namespace App\Filament\Tenant\Resources\Assets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                Select::make('outlet_id')
                    ->relationship('outlet', 'name'),
                Select::make('transaction_id')
                    ->relationship('transaction', 'id'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('asset_code')
                    ->required(),
                TextInput::make('category')
                    ->default(null),
                DatePicker::make('purchase_date'),
                TextInput::make('purchase_price')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
