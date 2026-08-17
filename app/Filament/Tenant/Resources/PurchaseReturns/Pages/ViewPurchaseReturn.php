<?php

namespace App\Filament\Tenant\Resources\PurchaseReturns\Pages;

use Filament\Resources\Pages\ViewRecord;
use App\Filament\Tenant\Resources\PurchaseReturns\PurchaseReturnResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\TextSize;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                
                // =========================================================
                // BAGIAN 1: INFORMASI RETUR (Ditambah Grand Total)
                // =========================================================
                Section::make('Informasi Retur Pembelian')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        // --- BARIS 1 ---
                        TextEntry::make('transaction_number')
                            ->label('Nomor Retur')
                            ->weight('bold')
                            ->color('danger'),

                        TextEntry::make('created_at')
                            ->label('Tanggal Retur')
                            ->dateTime('d M Y - H:i'),

                        TextEntry::make('supplier.name')
                            ->label('Nama Pemasok')
                            ->weight('bold'),

                        // --- BARIS 2 ---
                        TextEntry::make('referenceTransaction.transaction_number')
                            ->label('Ref. Nota PO Asli')
                            ->color('info')
                            ->weight('bold')
                            ->url(fn ($record) => $record->reference_id
                                ? \App\Filament\Tenant\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('view', ['record' => $record->reference_id])
                                : null
                            )
                            ->openUrlInNewTab(),

                        TextEntry::make('account.name')
                            ->label('Uang Masuk ke Kas/Rekening')
                            ->color('success'),

                        TextEntry::make('notes')
                            ->label('Alasan Retur / Catatan')
                            ->default('-'),

                        // --- BARIS 3 ---
                        // Ditempatkan di urutan ke-7 agar posisinya persis di bawah "Ref. Nota PO Asli"
                        TextEntry::make('grand_total')
                            ->label('Total Uang Kembali (Refund)')
                            ->money('IDR', locale: 'id')
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight('bold'),
                    ]),

                // =========================================================
                // BAGIAN 2: RINCIAN BARANG 
                // =========================================================
                Section::make('Rincian Barang yang Diretur')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name') 
                                    ->label('Nama Barang')
                                    ->weight('bold')
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                TextEntry::make('qty')
                                    ->label('Jumlah')
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                TextEntry::make('uom.name')
                                    ->label('Satuan')
                                    ->default('-')
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                TextEntry::make('cost_price')
                                    ->label('Harga Modal/Refund')
                                    ->money('IDR', locale: 'id')
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                TextEntry::make('subtotal')
                                    ->label('Total')
                                    ->money('IDR', locale: 'id')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->columnSpan(['default' => 12, 'md' => 2]),
                            ])
                            ->columns(12),
                    ]),
            ]);
    }
}