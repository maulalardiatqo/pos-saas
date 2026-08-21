<?php

namespace App\Filament\Tenant\Resources\SalesInvoices\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use Filament\Facades\Filament;

class SalesInvoiceForm
{
    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $itemSubtotal = (float) str_replace('.', '', $item['subtotal'] ?? 0);
            $subtotal += $itemSubtotal;
        }

        $discount = (float) str_replace('.', '', $get('discount') ?? 0);
        $tax = (float) str_replace('.', '', $get('tax') ?? 0);
        
        // Kalkulasi Potongan Poin
        $pointsToRedeem = (int) ($get('points_to_redeem') ?? 0);
        $pointValue = (float) (filament()->getTenant()->loyalty_point_value ?? 1);
        $pointDiscount = $pointsToRedeem * $pointValue;
        
        $grandTotal = $subtotal - $discount - $pointDiscount + $tax;
        if ($grandTotal < 0) $grandTotal = 0;
        
        $amountPaid = (float) str_replace('.', '', $get('amount_paid') ?? 0);

        $set('subtotal', $subtotal);
        $set('grand_total', $grandTotal);
        $set('amount_change', $amountPaid - $grandTotal);
    }

    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $schema
            ->columns(1)
            ->components([
            
            Section::make('Informasi Penjualan')
                ->schema([
                    Hidden::make('type')->default('invoice'),
                    Hidden::make('in_out')->default('in'),

                    Group::make([
                        TextInput::make('transaction_number')
                            ->label('Nomor Invoice')
                            ->default('INV-' . date('Ymd-His'))
                            ->required()
                            ->readOnly()
                            ->extraAttributes(['class' => 'font-mono font-bold text-lg']),
                        
                        DateTimePicker::make('created_at')
                            ->label('Waktu Transaksi')
                            ->default(now())
                            ->required(),
                        
                        Select::make('outlet_id')
                            ->relationship('outlet', 'name')
                            ->label('Untuk Outlet / Cabang')
                            ->default(fn () => $user?->outlet_id)
                            ->disabled(!$isOwnerOrPlatform)
                            ->dehydrated()
                            ->required(),
                    ])->columns(1),

                    Group::make([
                        Select::make('customer_id')
                            ->label('Pelanggan (Wajib)')
                            ->relationship('customer', 'name', fn($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->live() // Memungkinkan pembaruan saldo poin secara real-time
                            ->afterStateUpdated(fn (Set $set) => $set('points_to_redeem', 0))
                            ->required()
                            // ==============================================================
                            // PERBAIKAN: MODAL CREATE CUSTOMER
                            // ==============================================================
                            ->createOptionModalHeading('Tambah Pelanggan Baru')
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Kode Pelanggan')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id)
                                    )
                                    ->default(fn () => 'CUST-' . strtoupper(str()->random(5))),
                                    
                                TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(150),
                                    
                                Select::make('outlet_id')
                                    ->label('Pilih Outlet / Cabang')
                                    ->options(function () use ($isOwnerOrPlatform, $user) {
                                        $query = \App\Models\Outlet::where('company_id', filament()->getTenant()->id);
                                        if (!$isOwnerOrPlatform) $query->where('id', $user->outlet_id);
                                        return $query->pluck('name', 'id');
                                    })
                                    ->placeholder('Pelanggan Umum (Semua Outlet)')
                                    ->searchable()
                                    ->preload(),
                                    
                                TextInput::make('phone')->label('Nomor Telepon')->tel()->maxLength(20),
                                TextInput::make('email')->label('Email')->email()->maxLength(100),
                                Textarea::make('address')->label('Alamat Lengkap')->rows(2)->columnSpanFull(),

                                // TAMBAHAN KENDARAAN (BENGKEL MOTOR)
                                \Filament\Forms\Components\Repeater::make('vehicles')
                                    ->label('Daftar Kendaraan (Motor)')
                                    ->addActionLabel('Tambah Kendaraan')
                                    ->columnSpanFull()
                                    ->visible(fn () => data_get(Filament::getTenant()?->subscriptionPlan, 'code') === 'bengkel_motor')
                                    ->schema([
                                        \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                                            Select::make('jenis')
                                                ->label('Jenis Motor')
                                                ->options([
                                                    'matic'            => 'Matic',
                                                    'bebek'            => 'Bebek',
                                                    'sport'            => 'Sport',
                                                    'adventure'        => 'Adventure',
                                                    'motor elektronik' => 'Motor Elektronik',
                                                ])
                                                ->required(),

                                            TextInput::make('type')
                                                ->label('Tipe / Model')
                                                ->placeholder('Contoh: Honda Beat FI')
                                                ->required()
                                                ->maxLength(255),

                                            TextInput::make('nomor_plat')
                                                ->label('Nomor Plat')
                                                ->placeholder('Contoh: D 1234 ABC')
                                                ->required()
                                                ->maxLength(50)
                                                ->distinct()
                                                ->validationMessages([
                                                    'distinct' => 'Plat nomor ini tidak boleh sama dengan baris lain.',
                                                ])
                                                ->extraAttributes(['style' => 'text-transform: uppercase;'])
                                                ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '', (string) $state)))
                                                ->rule(function () {
                                                    return function (string $attribute, $value, \Closure $fail) {
                                                        $cleanedValue = strtoupper(str_replace(' ', '', (string) $value));
                                                        $companyId = Filament::getTenant()->id;
                                                        
                                                        $existingVehicle = \App\Models\CustomerVehicle::with('customer')
                                                            ->where('company_id', $companyId)
                                                            ->where('nomor_plat', $cleanedValue)
                                                            ->first();

                                                        if ($existingVehicle && $existingVehicle->customer) {
                                                            $fail("Nomor Plat Sudah Terdaftar Atas Nama " . $existingVehicle->customer->name);
                                                        }
                                                    };
                                                }),
                                        ])
                                    ])
                                    ->defaultItems(0)
                            ])
                            // ==============================================================
                            // PERBAIKAN: GUNAKAN createOptionUsing UNTUK MENYIMPAN MANUAL
                            // ==============================================================
                            ->createOptionUsing(function (array $data): string {
                                $tenantId = Filament::getTenant()->id;

                                // 1. Buat Customer
                                $customer = \App\Models\Customer::create([
                                    'company_id' => $tenantId,
                                    'outlet_id'  => $data['outlet_id'] ?? null,
                                    'code'       => $data['code'] ?? 'CUST-' . strtoupper(str()->random(5)),
                                    'name'       => $data['name'],
                                    'phone'      => $data['phone'] ?? null,
                                    'email'      => $data['email'] ?? null,
                                    'address'    => $data['address'] ?? null,
                                    'is_active'  => true,
                                ]);

                                // 2. Buat Kendaraan Jika Ada (Khusus Bengkel)
                                if (!empty($data['vehicles'])) {
                                    foreach ($data['vehicles'] as $vehicle) {
                                        \App\Models\CustomerVehicle::create([
                                            'customer_id' => $customer->id,
                                            'company_id'  => $tenantId,
                                            'jenis'       => $vehicle['jenis'],
                                            'type'        => $vehicle['type'],
                                            'nomor_plat'  => strtoupper(str_replace(' ', '', $vehicle['nomor_plat'])),
                                        ]);
                                    }
                                }

                                // 3. Kembalikan ID Customer agar Select otomatis memilihnya
                                return $customer->id;
                            }),
                        
                        Select::make('status')
                            ->label('Status Dokumen')
                            ->options([
                                'pending' => 'Tertunda (Belum Lunas)',
                                'completed' => 'Lunas (Completed)',
                            ])
                            ->default('pending')
                            ->disabled()
                            ->dehydrated(),
                            
                        TextInput::make('notes')
                            ->label('Catatan / Jatuh Tempo')
                            ->placeholder('Contoh: Tempo 14 Hari')
                            ->maxLength(255),
                    ])->columns(1),

                    Group::make([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Dibuat Oleh (Admin)')
                            ->default(fn () => auth()->id())
                            ->disabled()
                            ->dehydrated(),
                            
                        Select::make('payment_method')
                            ->label('Metode Pembayaran (DP)')
                            ->options([
                                'tempo' => 'Tempo (Kredit)',
                                'cash' => 'Cash',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS',
                            ])
                            ->default('tempo')
                            ->required(),
                            
                        Select::make('account_id')
                            ->label('Sumber Rekening (Khusus DP)')
                            ->options(function() {
                                return \App\Models\Account::where('company_id', filament()->getTenant()->id)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->helperText('Wajib diisi jika Pelanggan membayar Uang Muka.')
                            ->required(fn(Get $get) => (float) str_replace('.', '', $get('amount_paid')) > 0),
                    ])->columns(1),
                ])->columns(3),

            Section::make('Rangkuman Nilai Invoice')
                ->schema([
                    Group::make([
                        TextInput::make('subtotal')
                            ->label('Subtotal Invoice')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->readOnly(),

                        TextInput::make('discount')
                            ->label('Diskon Global')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),
                            
                        // FIELD TUKAR POIN
                        TextInput::make('points_to_redeem')
                            ->label('Tukar Poin (Potongan)')
                            ->numeric()
                            ->default(0)
                            ->live(onBlur: true)
                            ->maxValue(function (Get $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) return 0;
                                return Customer::find($customerId)?->points_balance ?? 0;
                            })
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
                            ->helperText(function (Get $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) return 'Pilih pelanggan untuk mengecek poin.';
                                $customer = Customer::find($customerId);
                                $pts = $customer ? $customer->points_balance : 0;
                                return "Sisa Poin Pelanggan: " . number_format($pts, 0, ',', '.') . " Pts";
                            }),

                        TextInput::make('tax')
                            ->label('Total Pajak')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                        TextInput::make('grand_total')
                            ->label('Grand Total')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->readOnly()
                            ->extraAttributes(['class' => 'font-bold bg-gray-50 dark:bg-gray-800']),
                    ])->columns(1),

                    Group::make([
                        TextInput::make('amount_paid')
                            ->label('Uang Muka (DP / Dibayarkan)')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                        TextInput::make('amount_change')
                            ->label('Kembalian / Sisa Hutang')
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->default(0)
                            ->readOnly()
                            ->extraAttributes(['class' => 'font-bold text-danger-600 bg-danger-50 dark:bg-danger-900']),
                    ])->columns(1),
                ])->columns(2),

            Section::make('Rincian Barang yang Dijual')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
                        ->deleteAction(fn ($action) => $action->after(fn (Get $get, Set $set) => self::updateTotals($get, $set)))
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            $factor = 1;
                            if (!empty($data['uom_id'])) {
                                $factor = DB::table('product_uoms')->where('product_id', $data['product_id'])->where('uom_id', $data['uom_id'])->value('conversion_factor') ?? 1;
                            }
                            $data['conversion_factor'] = (float) $factor;
                            $data['base_qty'] = ((float) ($data['qty'] ?? 1)) * $factor;
                            
                            $baseCost = DB::table('products')->where('id', $data['product_id'])->value('cost_price') ?? 0;
                            $data['cost_price'] = ((float) $baseCost) * $factor;
                            
                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            $factor = 1;
                            if (!empty($data['uom_id'])) {
                                $factor = DB::table('product_uoms')->where('product_id', $data['product_id'])->where('uom_id', $data['uom_id'])->value('conversion_factor') ?? 1;
                            }
                            $data['conversion_factor'] = (float) $factor;
                            $data['base_qty'] = ((float) ($data['qty'] ?? 1)) * $factor;
                            
                            $baseCost = DB::table('products')->where('id', $data['product_id'])->value('cost_price') ?? 0;
                            $data['cost_price'] = ((float) $baseCost) * $factor;
                            
                            return $data;
                        })
                        ->schema([
                           Select::make('product_id')
                                ->relationship('product', 'name', fn ($query) => $query->where('item_type', '!=', ''))
                                ->label('Item / Produk')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    $set('uom_id', null);
                                    $set('conversion_factor', 1);
                                    
                                    if ($state) {
                                        $product = DB::table('products')->where('id', $state)->first();
                                        if ($product) {
                                            $set('item_name', $product->name);
                                            $set('_base_price', $product->base_price);
                                            $set('_base_cost_price', $product->cost_price);
                                            $set('cost_price', $product->cost_price);
                                            
                                            // Tandai jika produk adalah Jasa atau Bundle
                                            $isServiceOrBundle = ($product->item_type === 'service' || in_array($product->product_type, ['bundle', 'recipe']));
                                            $set('_is_service_or_bundle', $isServiceOrBundle);

                                            // ==========================================================
                                            // PERBAIKAN: Langsung tembak harga jika Jasa/Bundle
                                            // ==========================================================
                                            if ($isServiceOrBundle) {
                                                $set('selling_price', number_format($product->base_price, 0, '', ''));
                                                
                                                $qty = (float) ($get('qty') ?: 1);
                                                $disc = (float) str_replace('.', '', $get('discount_amount') ?? 0);
                                                $set('subtotal', ($qty * $product->base_price) - $disc);
                                            } else {
                                                // Kosongkan harga agar user dipaksa pilih satuan dulu (untuk Barang Fisik)
                                                $set('selling_price', null);
                                                $set('subtotal', 0);
                                            }
                                        }
                                    } else {
                                        $set('_base_price', 0);
                                        $set('_base_cost_price', 0);
                                        $set('cost_price', 0);
                                        $set('_is_service_or_bundle', false);
                                        $set('selling_price', null);
                                        $set('subtotal', 0);
                                    }
                                })
                                // Lebarkan kolom otomatis jika Satuan disembunyikan
                                ->columnSpan(fn (Get $get) => $get('_is_service_or_bundle') ? 5 : 3),
                            
                            TextInput::make('qty')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $factor = (float) ($get('conversion_factor') ?? 1);
                                    $set('base_qty', (float) $get('qty') * $factor);
                                    
                                    $qty = (float) $get('qty');
                                    $price = (float) str_replace('.', '', $get('selling_price'));
                                    $disc = (float) str_replace('.', '', $get('discount_amount') ?? 0);
                                    $set('subtotal', ($qty * $price) - $disc);
                                })
                                ->columnSpan(1),
                                
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
                                // ==========================================================
                                // PERBAIKAN: Sembunyikan & lepas wajib isi jika Jasa/Bundle
                                // ==========================================================
                                ->hidden(fn (Get $get) => $get('_is_service_or_bundle'))
                                ->required(fn (Get $get) => !$get('_is_service_or_bundle'))
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
                                        
                                        $qty = (float) $get('qty') ?: 1;
                                        $set('base_qty', $qty * $factor);

                                        $baseCost = (float) ($get('_base_cost_price') ?? 0);
                                        $set('cost_price', $baseCost * $factor);

                                        $suggestedPrice = $pivotData && $pivotData->selling_price > 0 
                                            ? (float) $pivotData->selling_price 
                                            : ((float) ($get('_base_price') ?? 0) * $factor);
                                        
                                        $set('selling_price', number_format($suggestedPrice, 0, '', ''));
                                        
                                        $disc = (float) str_replace('.', '', $get('discount_amount') ?? 0);
                                        $set('subtotal', ($qty * $suggestedPrice) - $disc);
                                    }
                                })
                                ->columnSpan(2),
                                
                            TextInput::make('selling_price')
                                ->label('Harga Satuan')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) $get('qty');
                                    $price = (float) str_replace('.', '', $get('selling_price'));
                                    $disc = (float) str_replace('.', '', $get('discount_amount') ?? 0);
                                    $set('subtotal', ($qty * $price) - $disc);
                                })
                                ->columnSpan(2),

                            TextInput::make('discount_amount')
                                ->label('Diskon Item')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qty = (float) $get('qty');
                                    $price = (float) str_replace('.', '', $get('selling_price'));
                                    $disc = (float) str_replace('.', '', $get('discount_amount') ?? 0);
                                    $set('subtotal', ($qty * $price) - $disc);
                                })
                                ->columnSpan(2),

                            TextInput::make('subtotal')
                                ->label('Jml Bersih')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->default(0)
                                ->readOnly()
                                ->columnSpan(2),

                            Hidden::make('item_name')->dehydrated(),
                            Hidden::make('conversion_factor')->default(1)->dehydrated(),
                            Hidden::make('base_qty')->default(1)->dehydrated(),
                            Hidden::make('cost_price')->default(0)->dehydrated(),
                            Hidden::make('_base_price')->dehydrated(false),
                            Hidden::make('_base_cost_price')->dehydrated(false),
                            Hidden::make('discount_rate')->default(0),
                            Hidden::make('tax_rate')->default(0),
                            Hidden::make('tax_amount')->default(0),
                            
                            Hidden::make('_is_service_or_bundle')->default(false)->dehydrated(false),
                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->addable(true)
                        ->deletable(true)
                ]),
        ]);
    }
}