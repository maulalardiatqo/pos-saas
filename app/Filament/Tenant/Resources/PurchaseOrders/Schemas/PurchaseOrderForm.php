<?php

namespace App\Filament\Tenant\Resources\PurchaseOrders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Schemas\Components\Grid;

class PurchaseOrderForm
{
    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            // Bersihkan titik ribuan sebelum dikalkulasi
            $cost = (float) str_replace('.', '', $item['cost_price'] ?? 0); 
            $subtotal += ($qty * $cost);
        }

        $discount = (float) str_replace('.', '', $get('discount') ?? 0);
        $tax = (float) str_replace('.', '', $get('tax') ?? 0);
        
        $grandTotal = $subtotal - $discount + $tax;
        $amountPaid = (float) str_replace('.', '', $get('amount_paid') ?? 0);

        $set('subtotal', $subtotal);
        $set('grand_total', $grandTotal);
        
        $set('amount_change', $amountPaid > 0 ? $amountPaid - $grandTotal : 0);
    }

    public static function form(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $schema
            ->schema([
                Section::make('Informasi Pembelian')
                    ->schema([
                        // Set status otomatis jadi out (Uang Keluar)
                        Hidden::make('in_out')->default('out'),

                        Group::make([
                            TextInput::make('transaction_number')
                                ->label('Nomor PO / Nota')
                                ->default('PO-' . date('Ymd-His'))
                                ->required()
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
                                ->searchable()
                                ->preload()
                                ->live() // Trigger perubahan agar account_id ikut ter-refresh
                                ->afterStateUpdated(fn (Set $set) => $set('account_id', null))
                                ->required(),
                        ])->columns(1),

                        Group::make([
                            Select::make('supplier_id')
                                ->relationship('supplier', 'name', function (Builder $query) use ($user, $isOwnerOrPlatform) {
                                    $query->where('is_active', true);

                                    // Filter supplier berdasarkan outlet jika bukan owner
                                    if (!$isOwnerOrPlatform) {
                                        $query->where(function ($q) use ($user) {
                                            $q->whereNull('outlet_id')
                                              ->orWhere('outlet_id', $user?->outlet_id);
                                        });
                                    }

                                    return $query;
                                })
                                ->label('Pemasok (Vendor)')
                                ->searchable()
                                ->preload()
                                ->required()
                                // =========================================================
                                // FITUR TAMBAH SUPPLIER CEPAT (CREATE OPTION FORM)
                                // =========================================================
                                ->createOptionForm([
                                    Grid::make(2)->schema([
                                        TextInput::make('name')
                                            ->label('Nama Perusahaan')
                                            ->required()
                                            ->maxLength(150),
                                        
                                        Select::make('outlet_id')
                                            ->label('Lokasi Outlet / Cabang')
                                            ->options(function () use ($isOwnerOrPlatform, $user) {
                                                $query = \App\Models\Outlet::where('company_id', filament()->getTenant()->id);
                                                if (!$isOwnerOrPlatform) {
                                                    $query->where('id', $user->outlet_id);
                                                }
                                                return $query->pluck('name', 'id');
                                            })
                                            ->placeholder('Supplier Umum (Semua Cabang)')
                                            ->searchable()
                                            ->preload(),
                                            
                                        TextInput::make('contact_person')
                                            ->label('Nama Kontak (PIC)')
                                            ->maxLength(100),
                                            
                                        TextInput::make('phone')
                                            ->label('Nomor Telepon')
                                            ->tel()
                                            ->maxLength(20),
                                            
                                        \Filament\Forms\Components\Textarea::make('address')
                                            ->label('Alamat Lengkap')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                ])
                                ->createOptionAction(function (Action $action) {
                                    return $action
                                        ->modalHeading('Tambah Pemasok Baru')
                                        ->mutateFormDataUsing(function (array $data): array {
                                            // Menyuntikkan company_id dan kode secara otomatis saat disubmit
                                            $data['company_id'] = filament()->getTenant()->id;
                                            $data['code'] = 'SUP-' . strtoupper(str()->random(5));
                                            $data['is_active'] = true;
                                            return $data;
                                        });
                                }),
                            
                            Select::make('status')
                                ->label('Status Dokumen')
                                ->options([
                                    'pending'   => 'Tertunda (Pending)',
                                    'completed' => 'Selesai (Completed)',
                                    'cancelled' => 'Dibatalkan (Cancelled)',
                                ])
                                ->default('pending')
                                ->required(),
                        ])->columns(1),

                        Group::make([
                            Select::make('user_id')
                                ->relationship('user', 'name')
                                ->label('Dibuat Oleh (Purchaser)')
                                ->default(fn () => auth()->id())
                                ->disabled()
                                ->dehydrated(),
                            
                            Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash'        => 'Cash',
                                    'qris'        => 'QRIS',
                                    'transfer'    => 'Transfer',
                                    'debit_card'  => 'Debit Card',
                                    'credit_card' => 'Credit Card',
                                    'ewallet'     => 'E-Wallet',
                                ])
                                ->default('cash')
                                ->live() // Trigger perubahan agar account_id ikut ter-refresh
                                ->afterStateUpdated(fn (Set $set) => $set('account_id', null))
                                ->required(),

                            // AKUN KEUANGAN / SUMBER DANA (Filtered by Outlet & Payment Method)
                            Select::make('account_id')
                                ->label('Sumber Dana (Rekening/Kas)')
                                ->options(function (Get $get) use ($user, $isOwnerOrPlatform) {
                                    $outletId = $get('outlet_id');
                                    $paymentMethod = $get('payment_method');
                                    
                                    // Jika metode pembayaran atau outlet belum dipilih, kosongkan list account
                                    if (!$paymentMethod || !$outletId) {
                                        return [];
                                    }

                                    $query = \App\Models\Account::where('is_active', true)
                                        ->whereJsonContains('payment_methods', $paymentMethod);

                                    // Jika bukan Owner/Platform, hanya tampilkan akun milik outlet user ATAU akun Global
                                    if (!$isOwnerOrPlatform) {
                                        $query->where(function ($q) use ($user) {
                                            $q->whereNull('outlet_id')
                                              ->orWhere('outlet_id', $user?->outlet_id);
                                        });
                                    } else {
                                        // Jika Owner, filter berdasarkan outlet_id dari dropdown
                                        $query->where(function ($q) use ($outletId) {
                                            $q->whereNull('outlet_id')
                                              ->orWhere('outlet_id', $outletId);
                                        });
                                    }

                                    return $query->pluck('name', 'id');
                                })
                                ->searchable()
                                ->required(),
                        ])->columns(1),
                    ])
                    ->columns(3),

                // --- 2. RINCIAN BARANG ---
                Section::make('Rincian Barang yang Dipesan')
                    ->extraAttributes(['style' => 'overflow-x: auto; min-width: 100%;'])
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
                            ->deleteAction(fn ($action) => $action->after(fn (Get $get, Set $set) => self::updateTotals($get, $set)))
                            ->schema([
                                Select::make('product_id')
                                    ->relationship(
                                        name: 'product', 
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->where('item_type', '!=', 'service')
                                    )
                                    ->label('Item / Produk')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live() 
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('uom_id', null);
                                        $set('conversion_factor', 1);
                                        
                                        if ($state) {
                                            $product = DB::table('products')->where('id', $state)->first();
                                            if ($product) {
                                                $set('_base_cost_price', $product->cost_price);
                                            }
                                        } else {
                                            $set('_base_cost_price', 0);
                                        }
                                    })
                                    ->columnSpan(4),
                                    
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
                                        $cost = (float) str_replace('.', '', $get('cost_price'));
                                        $set('subtotal', $qty * $cost);
                                    })
                                    ->columnSpan(2),
                                    
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return [];
                                        }
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
                                            $qty = (float) $get('qty') ?: 1;
                                            $set('base_qty', $qty * $factor);

                                            $baseCostPrice = (float) ($get('_base_cost_price') ?? 0);
                                            $suggestedCostPrice = $baseCostPrice * $factor;
                                            
                                            $set('cost_price', number_format($suggestedCostPrice, 0, '', ''));
                                            $set('subtotal', $qty * $suggestedCostPrice);
                                        }
                                    })
                                    ->columnSpan(2),
                                    
                                Hidden::make('conversion_factor')->default(1)->dehydrated(),
                                Hidden::make('base_qty')->default(1)->dehydrated(),
                                Hidden::make('selling_price')->default(0),
                                Hidden::make('_base_cost_price')->dehydrated(false),
                                    
                                TextInput::make('cost_price')
                                    ->label('Harga Beli Satuan')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.') 
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) $get('qty');
                                        $cost = (float) str_replace('.', '', $get('cost_price'));
                                        $set('subtotal', $qty * $cost);
                                    })
                                    ->columnSpan(3),

                                TextInput::make('subtotal')
                                    ->label('Jumlah Bersih')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->default(0)
                                    ->readOnly()
                                    ->columnSpan(3),
                            ])
                            ->columns(14)
                            ->extraAttributes(['style' => 'min-width: 1000px;'])
                            ->defaultItems(1)
                            ->addable(true)
                            ->deletable(true)
                    ]),
                
                Section::make('Rangkuman Nilai PO')
                    ->schema([
                        Group::make([
                            TextInput::make('subtotal')
                                ->label('Subtotal PO')
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
                                ->label('Uang Dibayarkan (DP / Lunas)')
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
                                ->readOnly(),
                        ])->columns(1),
                    ])
                    ->columns(2),
            ])
            ->columns(1);
    }
}