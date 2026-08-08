<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LoyaltyRewardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('company_id'),
                TextEntry::make('name'),
                TextEntry::make('points_required')
                    ->numeric(),
                TextEntry::make('reward_type')
                    ->badge(),
                TextEntry::make('product_id')
                    ->placeholder('-'),
                TextEntry::make('discount_amount')
                    ->numeric()
                    ->placeholder('-'),
                ImageEntry::make('image')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
