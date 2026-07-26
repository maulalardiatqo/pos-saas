<?php

namespace App\Filament\Tenant\Resources\StockAdjustments\Schemas;

use Filament\Schemas\Schema; // <-- Gunakan Schema
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

class StockAdjustmentForm
{
    public static function configure(Schema $schema): Schema 
    {
        return $schema
            ->components([ // <-- Gunakan components(), bukan schema()
                Grid::make(3)->schema([
                    // KOLOM KIRI: Informasi Dokumen Induk (Lebar 2/3)
                    Grid::make(1)->schema([
                        Section::make('Informasi Penyesuaian')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('document_number')
                                        ->label('Nomor Dokumen')
                                        ->default(fn () => 'ADJ-' . strtoupper(str()->random(8)))
                                        ->required()
                                        ->readOnly()
                                        ->maxLength(50),

                                    DatePicker::make('date')
                                        ->label('Tanggal Penyesuaian')
                                        ->default(now())
                                        ->native(false)
                                        ->required(),

                                    Select::make('outlet_id')
                                        ->label('Cabang (Outlet)')
                                        ->relationship('outlet', 'name', function ($query) {
                                            $tenantId = Filament::getTenant()?->id;
                                            return $query->where('company_id', $tenantId);
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Select::make('status')
                                        ->label('Status Dokumen')
                                        ->options([
                                            'draft' => 'Draft (Belum Memotong Stok)',
                                            'completed' => 'Selesai (Stok Terpotong)',
                                        ])
                                        ->default('draft')
                                        ->required(),
                                ]),

                                Textarea::make('reason')
                                    ->label('Alasan Penyesuaian')
                                    ->placeholder('Cth: Barang rusak, hasil stock opname, dll.')
                                    ->rows(2),
                                    
                                Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                            ]),
                    ])->columnSpan(2),
                ])->columns(3),

                Section::make('Daftar Barang (Item)')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Pilih Produk')
                                    ->relationship('product', 'name', function ($query) {
                                        return $query->where('company_id', Filament::getTenant()?->id)
                                                    ->where('item_type', '!=', 'service'); 
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('uom_id', null);
                                        $set('conversion_factor', 1);
                                    })
                                    ->columnSpan(2),

                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];
                                        
                                        $uomIds = DB::table('product_uoms')
                                            ->where('product_id', $productId)
                                            ->whereNull('deleted_at')
                                            ->pluck('uom_id');

                                        return \App\Models\Uom::query()
                                            ->whereIn('id', $uomIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $productId = $get('product_id');
                                        if ($productId && $state) {
                                            $pivotData = DB::table('product_uoms')
                                                ->where('product_id', $productId)
                                                ->where('uom_id', $state)
                                                ->whereNull('deleted_at')
                                                ->first();

                                            $factor = $pivotData ? (float) $pivotData->conversion_factor : 1;
                                            $set('conversion_factor', $factor);
                                            $set('base_qty', (float) $get('quantity') * $factor);
                                        }
                                    })
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label('Jenis')
                                    ->options([
                                        'addition' => 'Tambah Stok (+)',
                                        'deduction' => 'Kurangi Stok (-)',
                                    ])
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $factor = (float) ($get('conversion_factor') ?? 1);
                                        $set('base_qty', (float) $get('quantity') * $factor);
                                    })
                                    ->columnSpan(1),

                                // Hidden fields untuk menjaga integritas data ke database
                                Hidden::make('conversion_factor')->default(1)->dehydrated(),
                                Hidden::make('base_qty')->default(1)->dehydrated(),

                                TextInput::make('remarks')
                                    ->label('Keterangan')
                                    ->placeholder('Opsional')
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->addActionLabel('Tambah Barang Lain')
                            ->defaultItems(1)
                            ->reorderable(true),
                    ]),
            ]);
    }
}