<?php

namespace App\Filament\Tenant\Resources\StockTransfers\Tables;

use App\Models\StockTransfer;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('No. Ref')
                    ->searchable()
                    ->weight('bold'),
                
                TextColumn::make('transfer_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('fromOutlet.name')
                    ->label('Dari')
                    ->icon('heroicon-m-building-storefront')
                    ->searchable(),

                TextColumn::make('toOutlet.name')
                    ->label('Tujuan')
                    ->icon('heroicon-m-truck')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'DRAFT',
                        'completed' => 'SELESAI',
                        default => strtoupper($state),
                    }),
                    
                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                Action::make('complete')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Selesaikan Mutasi Stok?')
                    ->modalDescription('Apakah Anda yakin? Tindakan ini akan memotong stok di lokasi asal dan menambahkannya ke lokasi tujuan secara permanen. Anda tidak bisa mengedit dokumen ini lagi.')
                    ->hidden(fn (StockTransfer $record) => $record->status === 'completed') 
                    ->action(function (StockTransfer $record) {
                        $record->markAsCompleted();
                        Notification::make()
                            ->title('Mutasi Berhasil!')
                            ->body('Stok telah dipindahkan.')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}