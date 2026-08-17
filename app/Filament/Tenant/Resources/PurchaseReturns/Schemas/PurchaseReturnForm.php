<?php

namespace App\Filament\Tenant\Resources\PurchaseReturns\Schemas;

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
use Filament\Support\RawJs;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReturnForm
{
    public static function updateTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $itemSubtotal = (float) str_replace('.', '', $item['subtotal'] ?? 0);
            $subtotal += $itemSubtotal;
        }

        $set('subtotal', $subtotal);
        $set('grand_total', $subtotal);
        $set('amount_paid', $subtotal); 
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Retur Pembelian')
                    ->schema([
                        Hidden::make('type')->default('refund'),
                        Hidden::make('in_out')->default('in'), 
                        Hidden::make('status')->default('completed'),

                        Group::make([
                            TextInput::make('transaction_number')
                                ->label('Nomor Retur')
                                ->default('RET-' . date('Ymd-His'))
                                ->required()
                                ->readOnly()
                                ->extraAttributes(['class' => 'font-mono font-bold text-lg']),
                            
                            DateTimePicker::make('created_at')
                                ->label('Waktu Retur')
                                ->default(now())
                                ->required(),
                        ])->columns(2),

                        Group::make([
                            Select::make('reference_id')
                                ->label('Pilih Nota Pembelian (PO) Asli')
                                ->options(function () {
                                    return Transaction::where('company_id', filament()->getTenant()->id)
                                        ->where('type', 'purchaseorder')
                                        ->where('status', 'completed')
                                        ->orderBy('created_at', 'desc')
                                        ->pluck('transaction_number', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (!$state) {
                                        $set('supplier_id', null);
                                        $set('account_id', null);
                                        $set('items', []);
                                        return;
                                    }

                                    $po = Transaction::with(['items.product', 'items.uom'])->find($state);
                                    if ($po) {
                                        $set('supplier_id', $po->supplier_id);
                                        $set('account_id', $po->account_id);

                                        // =========================================================
                                        // LOGIKA BARU: HITUNG BARANG YANG SUDAH PERNAH DIRETUR
                                        // =========================================================
                                        $returnedItems = DB::table('transaction_items')
                                            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                                            ->where('transactions.type', 'refund')
                                            ->where('transactions.reference_id', $po->id)
                                            ->where('transactions.status', 'completed')
                                            ->whereNull('transactions.deleted_at')
                                            ->select('transaction_items.product_id', DB::raw('SUM(transaction_items.base_qty) as total_returned'))
                                            ->groupBy('transaction_items.product_id')
                                            ->pluck('total_returned', 'product_id');

                                        $items = [];
                                        foreach($po->items as $item) {
                                            $returnedBaseQty = (float) ($returnedItems[$item->product_id] ?? 0);
                                            $remainingBaseQty = (float)$item->base_qty - $returnedBaseQty;

                                            // Jika barang ini sudah habis diretur semua, lewati (jangan dimunculkan)
                                            if ($remainingBaseQty <= 0) continue;

                                            $baseCost = $item->conversion_factor > 0 
                                                ? ($item->cost_price / $item->conversion_factor) 
                                                : $item->cost_price;

                                            // Tampilkan sisa yang boleh diretur dalam satuan asli pembeliannya
                                            $sisaBeli = $remainingBaseQty / ($item->conversion_factor ?: 1);

                                            $items[(string) Str::uuid()] = [
                                                'product_id'        => $item->product_id,
                                                'item_name'         => $item->item_name,
                                                'uom_id'            => $item->uom_id,
                                                'cost_price'        => $item->cost_price,
                                                'conversion_factor' => $item->conversion_factor,
                                                
                                                'purchased_info'    => "Sisa bisa diretur: {$sisaBeli} " . ($item->uom->name ?? 'Pcs'),
                                                'max_base_qty'      => $remainingBaseQty, 
                                                '_base_cost_price'  => $baseCost,       
                                                
                                                'qty'               => 0,
                                                'subtotal'          => 0,
                                            ];
                                        }
                                        $set('items', $items);
                                    }
                                }),

                            Select::make('supplier_id')
                                ->label('Pemasok (Supplier)')
                                ->relationship('supplier', 'name')
                                ->disabled() 
                                ->dehydrated()
                                ->required(),
                                
                            Select::make('account_id')
                                ->label('Terima Uang Refund Ke Rekening')
                                ->options(\App\Models\Account::where('company_id', filament()->getTenant()->id)->where('is_active', true)->pluck('name', 'id'))
                                ->required(),
                                
                            TextInput::make('notes')
                                ->label('Alasan Retur')
                                ->placeholder('Contoh: Barang cacat/rusak')
                                ->maxLength(255),
                        ])->columns(2),
                    ]),

                Section::make('Pilih Barang yang Diretur')
                    ->description('Isi jumlah dan pilih satuan barang yang ingin dikembalikan. Biarkan 0 jika tidak diretur.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('')
                            ->addable(false) 
                            ->deletable(false)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $factor = (float) ($data['conversion_factor'] ?? 1);
                                $data['base_qty'] = ((float) ($data['qty'] ?? 0)) * $factor;
                                $data['selling_price'] = 0; 
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $factor = (float) ($data['conversion_factor'] ?? 1);
                                $data['base_qty'] = ((float) ($data['qty'] ?? 0)) * $factor;
                                $data['selling_price'] = 0;
                                return $data;
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->label('Item / Produk')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(3),

                                TextInput::make('purchased_info')
                                    ->label('Sisa Kuota Retur')
                                    ->disabled()
                                    ->extraAttributes(['class' => 'text-primary-600 font-bold'])
                                    ->columnSpan(2),

                                TextInput::make('qty')
                                    ->label('Jml Diretur')
                                    ->numeric()
                                    ->step('0.001')
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->rules([
                                        fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $returnBaseQty = ((float)$value) * ((float)($get('conversion_factor') ?? 1));
                                            $maxBaseQty = (float)$get('max_base_qty');
                                            
                                            if ($returnBaseQty > $maxBaseQty) {
                                                $fail("Total retur melebihi sisa kuota yang bisa dikembalikan.");
                                            }
                                        },
                                    ])
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) $get('qty');
                                        $factor = (float) ($get('conversion_factor') ?? 1);
                                        $returnBaseQty = $qty * $factor;
                                        $maxBaseQty = (float) $get('max_base_qty');

                                        if ($returnBaseQty > $maxBaseQty) {
                                            $qty = $maxBaseQty / $factor;
                                            $set('qty', $qty);
                                        }

                                        $cost = (float) str_replace('.', '', $get('cost_price') ?? 0);
                                        $set('subtotal', $qty * $cost);
                                    })
                                    ->columnSpan(2),

                                Select::make('uom_id')
                                    ->label('Satuan Retur')
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
                                            
                                            $baseCost = (float) ($get('_base_cost_price') ?? 0);
                                            $newCostPrice = $baseCost * $factor;
                                            $set('cost_price', number_format($newCostPrice, 0, '', ''));

                                            $qty = (float) $get('qty');
                                            $set('subtotal', $qty * $newCostPrice);
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('subtotal')
                                    ->label('Total Refund')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->default(0)
                                    ->readOnly()
                                    ->columnSpan(3),

                                Hidden::make('cost_price')->dehydrated(),
                                Hidden::make('conversion_factor')->dehydrated(),
                                Hidden::make('item_name')->dehydrated(),
                                Hidden::make('max_base_qty')->dehydrated(false),
                                Hidden::make('_base_cost_price')->dehydrated(false),
                            ])
                            ->columns(12)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),
                    ]),

                Section::make('Rangkuman Refund')
                    ->schema([
                        Group::make([
                            TextInput::make('grand_total')
                                ->label('Total Uang Kembali (Refund)')
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->default(0)
                                ->readOnly()
                                ->extraAttributes(['class' => 'font-bold bg-success-50 text-success-600 dark:bg-success-900']),
                        ])->columns(1),
                        
                        Hidden::make('subtotal'),
                        Hidden::make('amount_paid'),
                    ])->columns(2),
            ]);
    }
}