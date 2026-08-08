<?php

namespace App\Filament\Tenant\Resources\StockTransfers\Schemas;

use App\Models\StockTransfer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('company.name')
                    ->label('Company'),
                TextEntry::make('reference_number'),
                TextEntry::make('fromOutlet.name')
                    ->label('From outlet'),
                TextEntry::make('toOutlet.name')
                    ->label('To outlet'),
                TextEntry::make('transfer_date')
                    ->date(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (StockTransfer $record): bool => $record->trashed()),
            ]);
    }
}
