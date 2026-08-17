<?php

namespace App\Filament\Tenant\Resources\PurchaseOrders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Models\Transaction;
use App\Filament\Tenant\Resources\PurchaseReturns\PurchaseReturnResource;

class PurchaseOrderForm
{
    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
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
                // =========================================================
                // 1. INFORMASI PEMBELIAN
                // =========================================================
                Section::make('Informasi Pembelian')
                    ->schema([
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
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('account_id', null))
                                ->required(),
                        ])->columns(1),

                        Group::make([
                            Select::make('supplier_id')
                                ->relationship('supplier', 'name', function (Builder $query) use ($user, $isOwnerOrPlatform) {
                                    $query->where('is_active', true);

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
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('account_id', null))
                                ->required(),

                            Select::make('account_id')
                                ->label('Sumber Dana (Rekening/Kas)')
                                ->options(function (Get $get) use ($user, $isOwnerOrPlatform) {
                                    $outletId = $get('outlet_id');
                                    $paymentMethod = $get('payment_method');
                                    
                                    if (!$paymentMethod || !$outletId) {
                                        return [];
                                    }

                                    $query = \App\Models\Account::where('is_active', true)
                                        ->whereJsonContains('payment_methods', $paymentMethod);

                                    if (!$isOwnerOrPlatform) {
                                        $query->where(function ($q) use ($user) {
                                            $q->whereNull('outlet_id')
                                              ->orWhere('outlet_id', $user?->outlet_id);
                                        });
                                    } else {
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

                // =========================================================
                // 2. RANGKUMAN NILAI PO (3 Kolom Per Baris)
                // =========================================================
                Section::make('Rangkuman Nilai PO')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
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
                    ]),

                // =========================================================
                // 3. TABS: RINCIAN ITEM & PENGEMBALIAN (RETURN)
                // =========================================================
                Tabs::make('POTabsDetail')
                    ->columnSpanFull()
                    ->tabs([
                        // TAB 1: RINCIAN BARANG DIBELI
                        Tab::make('Rincian Barang yang Dipesan')
                            ->icon('heroicon-o-shopping-bag')
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
                                                modifyQueryUsing: fn ($query) => $query->where('product_type', 'standard')->where('item_type', 'goods')
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

                        // TAB 2: PENGEMBALIAN (RETURN)
                        Tab::make('Pengembalian (Return)')
                            ->icon('heroicon-o-arrow-path-rounded-square')
                            ->badge(function ($record) {
                                if (!$record) return 0;
                                return Transaction::where('type', 'refund')
                                    ->where('reference_id', $record->id)
                                    ->whereNull('deleted_at')
                                    ->count();
                            })
                            ->badgeColor('danger')
                            ->schema([
                                Placeholder::make('returns_list')
                                    ->hiddenLabel()
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return new HtmlString('<div class="p-6 text-center text-gray-500 italic">Belum ada riwayat pengembalian (retur) untuk transaksi ini.</div>');
                                        }

                                        $returns = Transaction::with(['account'])
                                            ->where('type', 'refund')
                                            ->where('reference_id', $record->id)
                                            ->whereNull('deleted_at')
                                            ->orderBy('created_at', 'desc')
                                            ->get();

                                        if ($returns->isEmpty()) {
                                            return new HtmlString('<div class="p-6 text-center text-gray-500 italic">Belum ada riwayat pengembalian (retur) untuk transaksi ini.</div>');
                                        }

                                        $html = '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">';
                                        $html .= '<table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">';
                                        $html .= '<thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300"><tr>';
                                        $html .= '<th class="px-4 py-3">No. Retur</th>';
                                        $html .= '<th class="px-4 py-3">Tanggal Retur</th>';
                                        $html .= '<th class="px-4 py-3">Masuk ke Rekening/Kas</th>';
                                        $html .= '<th class="px-4 py-3">Alasan / Catatan</th>';
                                        $html .= '<th class="px-4 py-3 text-right">Total Refund</th>';
                                        $html .= '<th class="px-4 py-3 text-center">Aksi</th>';
                                        $html .= '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

                                        foreach ($returns as $ret) {
                                            $viewUrl = PurchaseReturnResource::getUrl('view', ['record' => $ret->id]);
                                            
                                            $html .= '<tr class="bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800">';
                                            $html .= '<td class="px-4 py-3 font-mono font-bold text-red-600 dark:text-red-400">' . e($ret->transaction_number) . '</td>';
                                            $html .= '<td class="px-4 py-3">' . e($ret->created_at->format('d M Y - H:i')) . '</td>';
                                            $html .= '<td class="px-4 py-3 text-emerald-600 font-medium">' . e($ret->account?->name ?? '-') . '</td>';
                                            $html .= '<td class="px-4 py-3">' . e($ret->notes ?? '-') . '</td>';
                                            $html .= '<td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">Rp ' . number_format($ret->grand_total, 0, ',', '.') . '</td>';
                                            $html .= '<td class="px-4 py-3 text-center">';
                                            $html .= '<a href="' . $viewUrl . '" target="_blank" class="inline-flex items-center gap-1 rounded bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300 transition-colors">';
                                            $html .= 'Lihat Detail &rarr;';
                                            $html .= '</a></td>';
                                            $html .= '</tr>';
                                        }

                                        $html .= '</tbody></table></div>';

                                        return new HtmlString($html);
                                    })
                            ]),
                    ]),
            ])
            ->columns(1);
    }
}