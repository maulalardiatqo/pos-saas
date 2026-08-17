<?php

namespace App\Filament\Tenant\Resources\Products\Schemas;

use Filament\Schemas\Schema;
use Filament\Facades\Filament;

// Layout Components
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

// Input Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Support\RawJs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;

class ProductForm
{
    /**
     * =========================================================================
     * FUNGSI HELPER: KALKULASI OTOMATIS HPP BUNDLE / RESEP
     * =========================================================================
     */
    public static function updateRootCostPrice(Get $get, Set $set): void
    {
        $type = $get('product_type');
        if (!in_array($type, ['bundle', 'recipe'])) return;

        $components = $get('components') ?? [];
        $totalCost = 0;

        foreach ($components as $comp) {
            $childId = $comp['child_product_id'] ?? null;
            $uomId = $comp['uom_id'] ?? null;
            $qty = (float) ($comp['quantity'] ?? 0);

            if ($childId) {
                $cost = (float) (\Illuminate\Support\Facades\DB::table('products')->where('id', $childId)->value('cost_price') ?? 0);
                
                if ($uomId) {
                    $factor = (float) (\Illuminate\Support\Facades\DB::table('product_uoms')
                        ->where('product_id', $childId)
                        ->where('uom_id', $uomId)
                        ->whereNull('deleted_at')
                        ->value('conversion_factor') ?? 1);
                    $cost *= $factor;
                }
                $totalCost += ($cost * $qty);
            }
        }

        $set('cost_price', number_format($totalCost, 0, '', ''));
    }

    public static function updateRepeaterCostPrice(Get $get, Set $set): void
    {
        $type = $get('../../product_type');
        if (!in_array($type, ['bundle', 'recipe'])) return;

        $components = $get('../../components') ?? [];
        $totalCost = 0;

        foreach ($components as $comp) {
            $childId = $comp['child_product_id'] ?? null;
            $uomId = $comp['uom_id'] ?? null;
            $qty = (float) ($comp['quantity'] ?? 0);

            if ($childId) {
                $cost = (float) (\Illuminate\Support\Facades\DB::table('products')->where('id', $childId)->value('cost_price') ?? 0);
                
                if ($uomId) {
                    $factor = (float) (\Illuminate\Support\Facades\DB::table('product_uoms')
                        ->where('product_id', $childId)
                        ->where('uom_id', $uomId)
                        ->whereNull('deleted_at')
                        ->value('conversion_factor') ?? 1);
                    $cost *= $factor;
                }
                $totalCost += ($cost * $qty);
            }
        }

        $set('../../cost_price', number_format($totalCost, 0, '', ''));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1) 
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
                            ->live() 
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
                                ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.category') === true)
                                // ==============================================
                                // POPUP TAMBAH KATEGORI CEPAT
                                // ==============================================
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nama Kategori')
                                        ->required()
                                        ->maxLength(100)
                                        ->unique('categories', 'name', modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id))
                                        ->helperText('Contoh: Minuman, Makanan Ringan, dsb.')
                                ])
                                ->createOptionAction(function (Action $action) {
                                    return $action
                                        ->modalHeading('Tambah Kategori Baru')
                                        ->mutateFormDataUsing(function (array $data): array {
                                            $data['company_id'] = Filament::getTenant()->id;
                                            return $data;
                                        });
                                }),

                            Select::make('base_uom_id')
                                ->label('Satuan Dasar (Terkecil)')
                                ->relationship('baseUom', 'name')
                                ->required(fn (Get $get) => $get('item_type') === 'goods') 
                                ->visible(fn (Get $get) => $get('item_type') === 'goods')
                                ->searchable()
                                ->preload()
                                ->disabled(fn (string $operation) => $operation === 'edit')
                                ->dehydrated() 
                                ->helperText('Pilih satuan paling KECIL (cth: Pcs, Gram).')
                                // ==============================================
                                // POPUP TAMBAH SATUAN DASAR CEPAT
                                // ==============================================
                                ->createOptionForm([
                                    Grid::make(2)->schema([
                                        TextInput::make('code')
                                            ->label('Kode Satuan')
                                            ->required()
                                            ->maxLength(20)
                                            ->unique('uoms', 'code', modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id))
                                            ->helperText('Contoh: PCS, KG, LUSIN'),
                                        TextInput::make('name')
                                            ->label('Nama Satuan')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Contoh: Pieces, Kilogram, Lusin'),
                                        TextInput::make('symbol')
                                            ->label('Simbol (Opsional)')
                                            ->maxLength(20)
                                            ->helperText('Contoh: pcs, kg, dz'),
                                        Toggle::make('is_active')
                                            ->label('Status Aktif')
                                            ->default(true)
                                            ->inline(false),
                                    ])
                                ])
                                ->createOptionAction(function (Action $action) {
                                    return $action
                                        ->modalHeading('Tambah Satuan Baru')
                                        ->mutateFormDataUsing(function (array $data): array {
                                            $data['company_id'] = Filament::getTenant()->id;
                                            return $data;
                                        });
                                }),

                            TextInput::make('cost_price')
                                ->label('Harga Modal (HPP)')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                ->default(0)
                                ->live(onBlur: true)
                                ->readOnly(fn (Get $get) => in_array($get('product_type'), ['bundle', 'recipe']))
                                ->helperText(fn (Get $get) => in_array($get('product_type'), ['bundle', 'recipe']) ? 'HPP otomatis dihitung dari komponen BOM.' : ''),

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
                // BLOK 3: TABS DETAIL (NETSUITE STYLE)
                // ==========================================
                Tabs::make('TabsDetail')
                    ->columnSpanFull() 
                    ->visible(fn (Get $get) => $get('item_type') === 'goods') 
                    ->tabs([
                        
                        // TAB 1: KONVERSI SATUAN
                        Tab::make('Konversi Satuan')
                            ->icon('heroicon-o-scale')
                            ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.multi_uom') === true)
                            ->schema([
                                Repeater::make('productUoms')
                                    ->relationship(
                                        modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_default', false)->orWhereNull('is_default')
                                    )
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
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                // ==============================================
                                                // POPUP TAMBAH SATUAN KHUSUS CEPAT
                                                // ==============================================
                                                ->createOptionForm([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('code')
                                                            ->label('Kode Satuan')
                                                            ->required()
                                                            ->maxLength(20)
                                                            ->unique('uoms', 'code', modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id))
                                                            ->helperText('Contoh: PCS, KG, LUSIN'),
                                                        TextInput::make('name')
                                                            ->label('Nama Satuan')
                                                            ->required()
                                                            ->maxLength(100)
                                                            ->helperText('Contoh: Pieces, Kilogram, Lusin'),
                                                        TextInput::make('symbol')
                                                            ->label('Simbol (Opsional)')
                                                            ->maxLength(20)
                                                            ->helperText('Contoh: pcs, kg, dz'),
                                                        Toggle::make('is_active')
                                                            ->label('Status Aktif')
                                                            ->default(true)
                                                            ->inline(false),
                                                    ])
                                                ])
                                                ->createOptionAction(function (Action $action) {
                                                    return $action
                                                        ->modalHeading('Tambah Satuan Baru')
                                                        ->mutateFormDataUsing(function (array $data): array {
                                                            $data['company_id'] = Filament::getTenant()->id;
                                                            return $data;
                                                        });
                                                }),

                                            TextInput::make('conversion_factor')
                                                ->label('Isi per Satuan Dasar')
                                                ->numeric()
                                                ->required()
                                                ->default(1)
                                                ->rules(['gt:1']) 
                                                ->validationMessages(['gt' => 'Isi konversi harus LEBIH BESAR DARI 1.'])
                                                ->helperText('Contoh: Jika 1 Dus = 12 Pcs, isi 12.')
                                                ->live(onBlur: true),

                                            TextInput::make('selling_price')
                                                ->label('Harga Jual Khusus')
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
                                                        $livewire->js("alert(`🛑 PERINGATAN HARGA SATUAN!\n\nHarga Jual (Rp " . number_format($selling, 0, ',', '.') . ") Lebih Kecil dari Harga Modal (Rp " . number_format($uomCost, 0, ',', '.') . ").`);");
                                                    }
                                                }),

                                            TextInput::make('barcode')
                                                ->label('Barcode Khusus')
                                                ->maxLength(100)
                                                ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan?->features, 'products.barcode') === true),
                                        ]),
                                    ])
                            ]),

                        // TAB 2: TIPE PRODUK / PAKET BOM
                        Tab::make('Tipe Produk & Paket (BOM)')
                            ->icon('heroicon-o-rectangle-group')
                            ->visible(function () {
                                $features = Filament::getTenant()?->subscriptionPlan?->features;
                                return data_get($features, 'products.bundle') === true || data_get($features, 'products.recipe') === true;
                            })
                            ->schema([
                                Select::make('product_type')
                                    ->label('Pilih Tipe Produk')
                                    ->options([
                                        'standard' => 'Produk Standar (Biasa)',
                                        'bundle'   => 'Paket Gabungan (Bundle)',
                                        'recipe'   => 'Menu dengan Resep (BOM)',
                                    ])
                                    ->default('standard')
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateRootCostPrice($get, $set)),

                                Repeater::make('components')
                                    ->relationship()
                                    ->label('Daftar Produk / Bahan Baku')
                                    ->addActionLabel('Tambah Item')
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => in_array($get('product_type'), ['bundle', 'recipe']))
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateRootCostPrice($get, $set))
                                    ->deleteAction(fn ($action) => $action->after(fn (Get $get, Set $set) => self::updateRootCostPrice($get, $set)))
                                    ->schema([
                                        Grid::make(5)->schema([
                                            Select::make('child_product_id')
                                                ->label('Pilih Produk/Bahan')
                                                ->relationship('childProduct', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set, Get $get) {
                                                    $set('uom_id', null);
                                                    $set('child_variant_id', null);
                                                    self::updateRepeaterCostPrice($get, $set);
                                                })
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                            Select::make('child_variant_id')
                                                ->label('Varian (Opsional)')
                                                ->relationship('childVariant', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->hidden(function (Get $get) {
                                                    $productId = $get('child_product_id');
                                                    if (!$productId) return false;
                                                    return \Illuminate\Support\Facades\DB::table('products')
                                                        ->where('id', $productId)->value('item_type') === 'service';
                                                }),

                                            Select::make('uom_id')
                                                ->label('Satuan')
                                                ->options(function (Get $get) {
                                                    $productId = $get('child_product_id');
                                                    if (!$productId) return [];
                                                    
                                                    $uomIds = \Illuminate\Support\Facades\DB::table('product_uoms')
                                                        ->where('product_id', $productId)
                                                        ->whereNull('deleted_at')
                                                        ->pluck('uom_id');

                                                    return \App\Models\Uom::query()
                                                        ->whereIn('id', $uomIds)
                                                        ->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->live() 
                                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateRepeaterCostPrice($get, $set))
                                                ->hidden(function (Get $get) {
                                                    $productId = $get('child_product_id');
                                                    if (!$productId) return false;
                                                    return \Illuminate\Support\Facades\DB::table('products')
                                                        ->where('id', $productId)->value('item_type') === 'service';
                                                })
                                                ->required(function (Get $get) {
                                                    $productId = $get('child_product_id');
                                                    if (!$productId) return false;
                                                    return \Illuminate\Support\Facades\DB::table('products')
                                                        ->where('id', $productId)->value('item_type') !== 'service';
                                                }),

                                            \Filament\Forms\Components\Placeholder::make('hpp_info')
                                                ->label('HPP / Modal')
                                                ->content(function (Get $get) {
                                                    $productId = $get('child_product_id');
                                                    $uomId = $get('uom_id');
                                                    
                                                    if (!$productId) return '-';
                                                    
                                                    $cost = (float) (\Illuminate\Support\Facades\DB::table('products')
                                                        ->where('id', $productId)->value('cost_price') ?? 0);
                                                    
                                                    if ($uomId) {
                                                        $factor = (float) (\Illuminate\Support\Facades\DB::table('product_uoms')
                                                            ->where('product_id', $productId)
                                                            ->where('uom_id', $uomId)
                                                            ->whereNull('deleted_at')
                                                            ->value('conversion_factor') ?? 1);
                                                        $cost = $cost * $factor;
                                                    }
                                                    
                                                    return 'Rp ' . number_format($cost, 0, ',', '.');
                                                }),

                                            TextInput::make('quantity')
                                                ->label('Jumlah Dibutuhkan')
                                                ->numeric()
                                                ->step('0.001')
                                                ->required()
                                                ->default(1)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateRepeaterCostPrice($get, $set)),
                                        ]),
                                    ])
                            ]),

                        // TAB 3: VARIAN PRODUK
                        Tab::make('Varian Produk')
                            ->icon('heroicon-o-swatch')
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
                                            TextInput::make('cost_price')
                                                ->label('Harga Modal (Varian)')
                                                ->prefix('Rp')
                                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                                ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 0, ',', '.') : '0')
                                                ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                                                ->required()
                                                ->default(0)
                                                ->live(onBlur: true),

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
                                                        $livewire->js("alert(`🛑 PERINGATAN HARGA VARIAN!\n\nHarga Jual Varian (Rp " . number_format($price, 0, ',', '.') . ") Lebih Kecil dari Harga Modal Varian (Rp " . number_format($cost, 0, ',', '.') . ").`);");
                                                    }
                                                }),
                                        ]),
                                    ])
                            ]),
                    ]),

            ]);
    }
}