<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LoyaltyRewardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('points_required')
                    ->required()
                    ->numeric(),
                Select::make('reward_type')
                    ->options(['product' => 'Product', 'discount' => 'Discount'])
                    ->default('product')
                    ->required(),
                TextInput::make('product_id')
                    ->default(null),
                TextInput::make('discount_amount')
                    ->numeric()
                    ->default(null),
                FileUpload::make('image')
                    ->image(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
