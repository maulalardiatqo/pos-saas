<?php

namespace App\Filament\Tenant\Resources\Reports\SalesReports\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;

class SalesReportsTable
{
    public static function configure(Table $table): Table
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('transaction_number')
                    ->label('No. Nota')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                // Kolom Cabang hanya dimunculkan jika yang login adalah Owner
                TextColumn::make('outlet.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible($isOwner)
                    ->badge()
                    ->color('info'),

                TextColumn::make('payment_method')
                    ->label('Metode Bayar')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color(fn ($state) => match($state) {
                        'cash' => 'success',
                        'qris' => 'warning',
                        'transfer' => 'primary',
                        default => 'gray',
                    }),

                // Omset dengan Total Summarizer
                TextColumn::make('grand_total')
                    ->label('Omset (Pendapatan)')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignment(Alignment::Right)
                    ->summarize([
                        Sum::make()
                            ->money('IDR', locale: 'id')
                            ->label('TOTAL OMSET')
                    ]),

                // Diskon dengan Total Summarizer
                TextColumn::make('discount')
                    ->label('Total Diskon')
                    ->money('IDR', locale: 'id')
                    ->alignment(Alignment::Right)
                    ->summarize([
                        Sum::make()
                            ->money('IDR', locale: 'id')
                            ->label('TOTAL DISKON')
                    ]),
            ])
            ->filters([
                // FILTER RENTANG TANGGAL
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('created_from')->label('Mulai Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal')->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([]) 
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->striped(); // Desain belang-belang agar mudah dibaca
    }
}