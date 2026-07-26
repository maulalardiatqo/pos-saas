<?php

namespace App\Filament\Tenant\Resources\StockAdjustments\Schemas;

use App\Models\StockAdjustment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockAdjustmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('company_id'),
                TextEntry::make('outlet_id'),
                TextEntry::make('user_id'),
                TextEntry::make('document_number'),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (StockAdjustment $record): bool => $record->trashed()),
            ]);
    }
}
