<?php

namespace App\Filament\Tenant\Resources\StockTransfers\Schemas;

use App\Models\StockTransfer;
use App\Models\Stock; // <-- IMPORT MODEL STOCK BARU KITA
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Closure;

class StockTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Surat Jalan / Mutasi')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('reference_number')
                                ->label('Nomor Referensi')
                                ->default(fn () => 'TRF-' . date('Ymd') . '-' . strtoupper(Str::random(4)))
                                ->required()
                                ->readOnly()
                                ->dehydrated(),

                            DatePicker::make('transfer_date')
                                ->label('Tanggal Transfer')
                                ->default(now())
                                ->native(false)
                                ->required(),

                            Select::make('status')
                                ->label('Status Mutasi')
                                ->options([
                                    'draft' => 'Draft (Belum Potong Stok)',
                                    'completed' => 'Completed (Selesai)',
                                ])
                                ->default('draft')
                                ->disabled()
                                ->dehydrated(),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('from_outlet_id')
                                ->label('Dari (Gudang/Toko Asal)')
                                ->relationship('fromOutlet', 'name', fn ($query) => $query->where('company_id', Filament::getTenant()->id))
                                ->searchable()
                                ->preload()
                                ->live() 
                                ->afterStateUpdated(function (Set $set) {
                                    $set('items', []);
                                })
                                ->required(),

                            Select::make('to_outlet_id')
                                ->label('Ke (Mobil Sales / Toko Tujuan)')
                                ->relationship('toOutlet', 'name', fn ($query) => $query->where('company_id', Filament::getTenant()->id))
                                ->searchable()
                                ->preload()
                                ->different('from_outlet_id')
                                ->validationMessages([
                                    'different' => 'Outlet tujuan tidak boleh sama dengan outlet asal.',
                                ])
                                ->required(),
                        ]),

                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->placeholder('Contoh: Dititipkan ke supir Budi / Barang retur')
                            ->columnSpanFull(),
                    ]),

                Section::make('Daftar Barang yang Dipindah')
                    ->columnSpanFull()
                    ->description(fn (Get $get) => $get('from_outlet_id') == null ? 'Pilih Gudang Asal terlebih dahulu sebelum memilih barang.' : 'Pastikan jumlah tidak melebihi stok gudang asal.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('product_id')
                                        ->label('Pilih Produk')
                                        ->relationship('product', 'name', fn ($query) => $query->where('company_id', Filament::getTenant()->id)->where('item_type', 'goods'))
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            $fromOutletId = $get('../../from_outlet_id');

                                            if ($state && $fromOutletId) {
                                                // PERBAIKAN: Gunakan Tabel Stocks untuk mengecek ketersediaan
                                                $stockRecord = Stock::where('product_id', $state)
                                                    ->where('outlet_id', $fromOutletId)
                                                    ->first();

                                                $stock = $stockRecord ? (float) $stockRecord->qty : 0;
                                                $set('available_stock', $stock);
                                            } else {
                                                $set('available_stock', 0);
                                            }
                                        }),

                                    Hidden::make('available_stock')
                                        ->default(0)
                                        ->dehydrated(false),

                                    TextInput::make('quantity')
                                        ->label('Kuantitas (Qty)')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->minValue(1)
                                        ->hint(fn (Get $get) => 'Stok di Gudang Asal: ' . ($get('available_stock') ?? 0))
                                        ->hintColor(fn (Get $get) => ((float) $get('available_stock') > 0) ? 'success' : 'danger')
                                        ->rules([
                                            fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                                $available = (float) $get('available_stock');
                                                if ((float) $value > $available) {
                                                    $fail("Kuantitas melebihi sisa stok di Gudang Asal (Maks: {$available}).");
                                                }
                                            },
                                        ]),
                                ]),
                            ])
                            ->addActionLabel('Tambah Barang')
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->hidden(fn (Get $get) => $get('from_outlet_id') == null),
                    ]),
            ])
            ->disabled(fn (?StockTransfer $record) => $record !== null && $record->status === 'completed');
    }
}