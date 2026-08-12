<?php

namespace App\Filament\Tenant\Resources\Products\Schemas;

use Filament\Schemas\Schema;
use Filament\Facades\Filament;

// Layout Components
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

// Input Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Support\RawJs;
use Filament\Schemas\Components\Utilities\Get;

// WAJIB DI-IMPORT UNTUK POP-UP VALIDASI
use Filament\Notifications\Notification;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ==========================================
                // BLOK 1: INFORMASI UTAMA
                // ==========================================
                Section::make('Informasi Utama')
                    ->schema([
                        FileUpload::make('image_url')
                            ->label('Foto Produk')
                            ->image()
                            ->directory('products')
                            ->disk('public')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                        Select::make('item_type')
                            ->label('Jenis Product')
                            ->options([
                                'goods' => 'Barang Fisik',
                                'service' => 'Jasa / Layanan',
                            ])
                            ->default('goods')
                            ->required()
                            ->helperText('Pilih "Jasa" jika item ini tidak memerlukan pelacakan stok gudang.')
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('sku')
                                ->label('SKU (Stock Keeping Unit)')
                                ->maxLength(100),

                            TextInput::make('barcode')
                                ->label('Barcode Utama')
                                ->maxLength(100)
                                ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),
                        ]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // ==========================================
                // BLOK 2: PENGATURAN & HARGA
                // ==========================================
                Section::make('Pengaturan & Harga')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->label('Kategori')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.category') === true),

                            Select::make('base_uom_id')
                                ->label('Satuan Dasar (Terkecil)')
                                ->relationship('baseUom', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->disabled(fn (string $operation) => $operation === 'edit')
                                ->dehydrated() 
                                ->helperText('Pilih satuan paling KECIL (cth: Pcs, Gram).'),

                            TextInput::make('cost_price')
                                ->label('Harga Modal (HPP)')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                ->default(0)
                                ->live(onBlur: true),

                            // PERBAIKAN 2: Tambahkan validasi Pop-up Warning (Notification)
                            TextInput::make('base_price')
                                ->label('Harga Jual Dasar')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                ->required()
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $get, \Livewire\Component $livewire) {
                                    $cost = (float) str_replace('.', '', (string) $get('cost_price'));
                                    $base = (float) str_replace('.', '', (string) $state);
                                    
                                    if ($base > 0 && $base < $cost) {
                                        $livewire->js("alert(`🛑 PERINGATAN HARGA!\n\nHarga Jual Dasar (Rp " . number_format($base, 0, ',', '.') . ") Lebih Kecil dari Harga Modal HPP (Rp " . number_format($cost, 0, ',', '.') . ").\n\nMohon pastikan Anda tidak salah input!`);");
                                    }
                                }),
                        ]),
                    ]),

                // ==========================================
                // BLOK 3: MULTI-UOM
                // ==========================================
                Section::make('Konversi Satuan & Harga Khusus')
                    ->description('Tambahkan jika produk ini dijual dalam bentuk Lusin, Karton, dll.')
                    ->collapsible()
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.multi_uom') === true)
                    ->schema([
                        Repeater::make('productUoms')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah Satuan Khusus')
                            ->columnSpanFull()
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('uom_id')
                                        ->label('Satuan Khusus')
                                        ->relationship(
                                            name: 'uom', 
                                            titleAttribute: 'name',
                                            modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query, Get $get) {
                                                $baseUom = $get('../../base_uom_id');
                                                if ($baseUom) {
                                                    $query->where('id', '!=', $baseUom);
                                                }
                                            }
                                        )
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                    TextInput::make('conversion_factor')
                                        ->label('Isi per Satuan Dasar')
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->rules(['gt:1']) 
                                        ->validationMessages([
                                            'gt' => 'Isi konversi harus LEBIH BESAR DARI 1. (Karena Satuan Dasar adalah yang terkecil)',
                                        ])
                                        ->helperText('Contoh: Jika 1 Dus = 12 Pcs, isi 12.')
                                        ->live(onBlur: true),
                                    TextInput::make('selling_price')
                                        ->label('Harga Jual')
                                        ->prefix('Rp')
                                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                        ->required()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, $get, \Livewire\Component $livewire) {
                                            $parentCost = (float) str_replace('.', '', (string) $get('../../cost_price'));
                                            $conversion = (float) $get('conversion_factor') ?: 1;
                                            
                                            $uomCost = $parentCost * $conversion;
                                            $selling = (float) str_replace('.', '', (string) $state);
                                            
                                            if ($selling > 0 && $selling < $uomCost) {
                                                $livewire->js("alert(`🛑 PERINGATAN HARGA SATUAN KHUSUS!\n\nHarga Jual (Rp " . number_format($selling, 0, ',', '.') . ") Lebih Kecil dari Harga Modal satuan ini (Rp " . number_format($uomCost, 0, ',', '.') . ").\n\nMohon periksa kembali!`);");
                                            }
                                        }),

                                    TextInput::make('barcode')
                                        ->label('Barcode Khusus')
                                        ->maxLength(100)
                                        ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),
                                ]),

                                Toggle::make('is_default')
                                    ->label('Default POS')
                                    ->inline(false)
                                    ->default(false),
                            ])
                    ]),

                // ==========================================
                // BLOK 4: VARIAN PRODUK
                // ==========================================
                Section::make('Varian Produk')
                    ->description('Tambahkan variasi produk seperti ukuran atau warna (contoh: Merah - XL).')
                    ->collapsible()
                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.variant') === true)
                    ->schema([
                        Toggle::make('has_variants')
                            ->label('Produk ini memiliki varian')
                            ->live()
                            ->default(false),

                        Repeater::make('variants')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah Varian')
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('has_variants'))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')
                                        ->label('Nama Varian')
                                        ->placeholder('Cth: Hitam - L')
                                        ->required()
                                        ->maxLength(150),
                                    
                                    TextInput::make('sku')
                                        ->label('SKU Varian')
                                        ->maxLength(100),

                                    TextInput::make('barcode')
                                        ->label('Barcode Varian')
                                        ->maxLength(100)
                                        ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),
                                ]),

                                Grid::make(2)->schema([
                                    // PERBAIKAN 4
                                    TextInput::make('cost_price')
                                        ->label('Harga Modal (Varian)')
                                        ->prefix('Rp')
                                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                        ->required()
                                        ->default(0)
                                        ->live(onBlur: true),

                                    // PERBAIKAN 5
                                    TextInput::make('price')
                                        ->label('Harga Jual (Varian)')
                                        ->prefix('Rp')
                                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                        ->required()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, $get, \Livewire\Component $livewire) {
                                            $cost = (float) str_replace('.', '', (string) $get('cost_price'));
                                            $price = (float) str_replace('.', '', (string) $state);
                                            
                                            if ($price > 0 && $price < $cost) {
                                                $livewire->js("alert(`🛑 PERINGATAN HARGA VARIAN!\n\nHarga Jual Varian (Rp " . number_format($price, 0, ',', '.') . ") Lebih Kecil dari Harga Modal Varian (Rp " . number_format($cost, 0, ',', '.') . ").\n\nMohon periksa kembali!`);");
                                            }
                                        }),
                                ]),
                            ])
                    ]),

                // ==========================================
                // BLOK 5: BUNDLE / RESEP (BOM)
                // ==========================================
                Section::make('Isi Paket / Resep (BOM)')
                    ->description('Tentukan produk/bahan baku yang membentuk produk ini.')
                    ->collapsible()
                    ->visible(function () {
                        $features = Filament::getTenant()?->subscriptionPlan?->features;
                        $hasBundle = data_get($features, 'products.bundle') === true;
                        $hasRecipe = data_get($features, 'products.recipe') === true;

                        return $hasBundle || $hasRecipe;
                    })
                    ->schema([
                        Select::make('product_type')
                            ->label('Tipe Produk')
                            ->options([
                                'standard' => 'Produk Standar (Biasa)',
                                'bundle'   => 'Paket Gabungan (Bundle)',
                                'recipe'   => 'Menu dengan Resep (BOM)',
                            ])
                            ->default('standard')
                            ->live()
                            ->required(),

                        Repeater::make('components')
                            ->relationship()
                            ->label('Daftar Produk / Bahan Baku')
                            ->addActionLabel('Tambah Item')
                            ->columnSpanFull()
                            ->visible(fn ($get) => in_array($get('product_type'), ['bundle', 'recipe']))
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('child_product_id')
                                        ->label('Pilih Produk/Bahan')
                                        ->relationship('childProduct', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                    Select::make('child_variant_id')
                                        ->label('Varian (Opsional)')
                                        ->relationship('childVariant', 'name')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('quantity')
                                        ->label('Jumlah Dibutuhkan')
                                        ->numeric()
                                        ->step('0.001')
                                        ->required()
                                        ->default(1),
                                ]),
                            ])
                    ]),

            ]);
    }
}