<?php

namespace App\Filament\Tenant\Resources\GiftCards\Schemas;

use App\Models\GiftCard;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GiftCardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('company_id'),
                TextEntry::make('customer_id')
                    ->placeholder('-'),
                TextEntry::make('card_number'),
                TextEntry::make('balance')
                    ->numeric(),
                TextEntry::make('expiry_date')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (GiftCard $record): bool => $record->trashed()),
            ]);
    }
}
