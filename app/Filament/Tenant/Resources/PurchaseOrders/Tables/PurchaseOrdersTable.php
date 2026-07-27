<?php

namespace App\Filament\Tenant\Resources\PurchaseOrders\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('No. PO')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Nomor PO disalin!'),
                
                TextColumn::make('created_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->searchable(),
                
                TextColumn::make('supplier.name')
                    ->label('Pemasok (Vendor)')
                    ->searchable()
                    ->default('Tanpa Pemasok'),

                TextColumn::make('grand_total')
                    ->label('Total PO')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total Nilai')
                            ->money('IDR', locale: 'id')
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning', // pending
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => strtoupper($state),
                    }),
            ])
            ->defaultSort('created_at', 'desc') 
            ->filters([
                SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Filter Outlet')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->isOwner() || auth()->user()?->isPlatform()),

                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Filter Pemasok')
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->label('Filter Status'),
                    
                Filter::make('created_at')
                    ->label('Rentang Waktu')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_from')->label('Dari Tanggal'),
                                DatePicker::make('date_until')->label('Sampai Tanggal'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                ViewAction::make()->label('Detail'),
                EditAction::make()->label('Edit'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}