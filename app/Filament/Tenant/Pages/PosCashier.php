<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Stock; 
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\PointHistory;
use App\Models\Account;
use App\Models\Voucher;
use App\Models\Customer;
use App\Models\PosSession;
use App\Models\LoyaltyReward;
use App\Services\MidtransService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Actions\Action; 
use Filament\Forms\Components\TextInput; 
use Filament\Forms\Components\Select; 
use Filament\Forms\Components\Textarea; 

class PosCashier extends Page
{
    protected string $view = 'filament.tenant.pages.pos-cashier';
    protected static ?string $slug = 'pos/cashier';

    // Menghilangkan Heading bawaan Filament agar lebih bersih
    public function getHeading(): string|Htmlable { return ''; }
    
    public static function getNavigationLabel(): string { return 'Penjualan (POS)'; }
    public static function getNavigationIcon(): string|BackedEnum|null { return 'heroicon-o-shopping-cart'; }

    // Livewire States
    public $search = '';
    public bool $isScanMode = false; // State Mode Scan
    public $activeCategory = 'all';
    public $cart = [];
    public $discount = 0;
    public $amountPaid = 0;
    public $pointsToRedeem = 0;
    
    // PROPERTI ITEM CUSTOM (MANUAL INPUT)
    public $customItemName = '';
    public $customItemCost = '';
    public $customItemPrice = '';

    // PROPERTI CRM & PEMBAYARAN
    public $customerSearch = ''; // State khusus ketikan cari pelanggan
    public $customerId = null;
    public $customerInfo = null; 
    public $voucherCode = ''; 
    public $appliedVoucher = null;
    public $activeSession = null;
    public $openingAmount = 0;
    public $sessionNotes = '';
    public $paymentMethod = '';
    public $accountId = null;
    public $claimedRewards = [];

    public function mount()
    {
        if (filament()->getTenant()->hasFeature('finance.closing_shift')) {
            $this->checkActiveSession();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FUNGSI TAMBAH PELANGGAN CEPAT (MODAL FILAMENT)
    |--------------------------------------------------------------------------
    */
    public function createCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('')
            ->icon('heroicon-m-plus')
            ->color('warning') // Mengubah warna tombol menjadi kuning/oranye
            ->tooltip('Tambah Pelanggan Baru')
            ->extraAttributes(['style' => 'height: 38px; width: 38px; display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-left: 0.5rem;'])
            ->form([
                TextInput::make('code')
                    ->label('Kode Pelanggan')
                    ->required()
                    ->maxLength(50)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where(
                            'company_id',
                            Filament::getTenant()->id
                        )
                    )
                    ->default(fn () => 'CUST-' . strtoupper(str()->random(5))),
                    
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(150),
                    
                Select::make('outlet_id')
                    ->label('Pilih Outlet / Cabang')
                    ->options(function () {
                        $user = auth()->user();
                        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());
                        $query = \App\Models\Outlet::where('company_id', filament()->getTenant()->id);
                        if (!$isOwnerOrPlatform) {
                            $query->where('id', $user->outlet_id);
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->placeholder('Pelanggan Umum (Semua Outlet)')
                    ->helperText('Kosongkan jika pelanggan ini bisa berbelanja di semua cabang (Global).')
                    ->searchable()
                    ->preload(),
                    
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->tel()
                    ->maxLength(20),
                    
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(100),
                    
                Textarea::make('address')
                    ->label('Alamat Lengkap')
                    ->rows(3),

                // =================================================================
                // TAMBAHAN: DAFTAR KENDARAAN (Hanya muncul jika bengkel_motor)
                // =================================================================
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
                                // ==========================================================
                                // PERBAIKAN: Validasi unik dan terdaftar (Sama dengan CustomerForm)
                                // ==========================================================
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
            ->action(function (array $data) {
                $tenant = filament()->getTenant();
                
                // 1. Simpan Data Customer Utama
                $customer = Customer::create([
                    'company_id' => $tenant->id,
                    'outlet_id'  => $data['outlet_id'] ?? null,
                    'code'       => 'CUST-' . strtoupper(str()->random(5)),
                    'name'       => $data['name'],
                    'phone'      => $data['phone'] ?? null,
                    'email'      => $data['email'] ?? null,
                    'address'    => $data['address'] ?? null,
                    'is_active'  => true,
                ]);

                // 2. Simpan Data Kendaraan (Jika Ada)
                if (!empty($data['vehicles'])) {
                    foreach ($data['vehicles'] as $vehicle) {
                        \App\Models\CustomerVehicle::create([
                            'customer_id' => $customer->id,
                            'company_id'  => $tenant->id,
                            'jenis'       => $vehicle['jenis'],
                            'type'        => $vehicle['type'],
                            // Paksa nomor plat menjadi huruf kapital saat masuk ke database
                            'nomor_plat'  => strtoupper(str_replace(' ', '', $vehicle['nomor_plat'])),
                        ]);
                    }
                }

                // 3. Auto select pelanggan yang baru dibuat di Kasir
                $this->selectCustomer($customer->id);
                Notification::make()->title('Pelanggan & Kendaraan berhasil ditambahkan!')->success()->send();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | FUNGSI PENCARIAN SUPER KILAT (BARCODE SCANNER)
    |--------------------------------------------------------------------------
    */
    public function handleSearchEnter($scannedCode = null)
    {
        $code = $scannedCode ?: $this->search;
        if (empty(trim($code))) return;
        $searchStr = strtolower(trim($code));

        $dbProduct = Product::where('company_id', filament()->getTenant()->id)
            ->where(function($q) use ($searchStr) {
                $q->where('sku', $searchStr)
                  ->orWhere('barcode', $searchStr)
                  ->orWhereIn('id', function ($subQuery) use ($searchStr) {
                      $subQuery->select('product_id')
                               ->from('product_uoms')
                               ->where('barcode', $searchStr)
                               ->whereNull('deleted_at'); 
                  });
            })->first();
            
        if ($dbProduct) {
            $this->addToCart($dbProduct->id);
            $this->search = ''; 
            $this->dispatch('focus-search');
        } else {
            Notification::make()->title('Barcode/SKU tidak ditemukan!')->warning()->send();
            $this->search = ''; 
            $this->dispatch('focus-search');
        }
    }
    
    public function getCategoriesProperty()
    {
        return Category::where('company_id', filament()->getTenant()?->id)->get();
    }

    public function getProductsProperty()
    {
        $user = auth()->user();
        $tenantId = filament()->getTenant()?->id;
        $outletId = $user->outlet_id;

        $latestStockSubquery = Stock::selectRaw('COALESCE(qty, 0)')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $outletId)
            ->limit(1);

        return Product::query()
            ->where('products.company_id', $tenantId)
            ->when($user->isCashier() && $outletId, function ($query) use ($outletId) {
                $query->join('product_outlets', function ($join) use ($outletId) {
                    $join->on('products.id', '=', 'product_outlets.product_id')
                         ->where('product_outlets.outlet_id', '=', $outletId);
                });
            })
            ->leftJoin('uoms as base_uoms', 'products.base_uom_id', '=', 'base_uoms.id')
            ->leftJoin('product_uoms', function ($join) {
                $join->on('products.id', '=', 'product_uoms.product_id')
                     ->where('product_uoms.is_default', true)
                     ->whereNull('product_uoms.deleted_at'); 
            })
            ->leftJoin('uoms as variant_uoms', 'product_uoms.uom_id', '=', 'variant_uoms.id')
            ->select([
                'products.id', 'products.name', 'products.image_url', 'products.item_type',
                'products.product_type', 'products.base_price as price', 'products.cost_price as cost', 
                DB::raw('COALESCE(product_uoms.barcode, products.barcode) as barcode'),
                'products.sku', 
                DB::raw('COALESCE(product_uoms.uom_id, products.base_uom_id) as uom_id'),
                DB::raw("COALESCE(variant_uoms.name, base_uoms.name, '-') as uom_name"),
                DB::raw('COALESCE(product_uoms.conversion_factor, 1) as conversion_factor')
            ])
            ->selectSub($latestStockSubquery, 'current_stock')
            ->when($this->search, function ($q) {
                if (!$this->isScanMode) {
                    $q->where(function($query) {
                        $query->where('products.name', 'like', "%{$this->search}%")
                              ->orWhere('products.sku', 'like', "%{$this->search}%")
                              ->orWhere('products.barcode', 'like', "%{$this->search}%")
                              ->orWhere('product_uoms.barcode', 'like', "%{$this->search}%");
                    });
                }
            })
            ->when($this->activeCategory !== 'all', fn ($q) => $q->where('products.category_id', $this->activeCategory))
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIKA PENCARIAN & PEMILIHAN PELANGGAN DINAMIS
    |--------------------------------------------------------------------------
    */
    public function getCustomerSearchResultsProperty()
    {
        if (empty(trim($this->customerSearch))) {
            return [];
        }

        $keyword = trim(strtolower($this->customerSearch));
        // Hilangkan spasi pada inputan pencarian agar lebih toleran (Cth: cari "D1234" ketemu "D 1234 ABC")
        $keywordNoSpace = str_replace(' ', '', $keyword);
        
        $tenant = filament()->getTenant();
        $outletId = auth()->user()->outlet_id;
        $isBengkel = data_get($tenant?->subscriptionPlan, 'code') === 'bengkel_motor';

        $query = Customer::where('company_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($query) use ($outletId) {
                $query->whereNull('outlet_id')
                      ->orWhere('outlet_id', $outletId);
            });

        // Jika bengkel, muat juga relasi vehicles agar bisa ditampilkan di UI
        if ($isBengkel) {
            $query->with('vehicles');
        }

        $query->where(function($q) use ($keyword, $keywordNoSpace, $isBengkel) {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
              ->orWhereRaw('LOWER(code) LIKE ?', ["%{$keyword}%"])
              ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$keyword}%"]);
              
            // Logika pencarian ekstra ke tabel kendaraan (Nomor Plat)
            if ($isBengkel) {
                $q->orWhereHas('vehicles', function ($vQ) use ($keyword, $keywordNoSpace) {
                    $vQ->whereRaw('LOWER(nomor_plat) LIKE ?', ["%{$keyword}%"])
                       ->orWhereRaw("REPLACE(LOWER(nomor_plat), ' ', '') LIKE ?", ["%{$keywordNoSpace}%"]);
                });
            }
        });

        return $query->take(5)->get();
    }

    public function selectCustomer($id)
    {
        $this->customerId = $id;
        $this->customerSearch = ''; // Kosongkan ketikan pencarian
        $this->updatedCustomerId($id);
    }

    public function clearCustomer()
    {
        $this->customerId = null;
        $this->customerSearch = '';
        $this->updatedCustomerId(null);
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $isBengkel = data_get(filament()->getTenant()?->subscriptionPlan, 'code') === 'bengkel_motor';
            $with = ['membership'];
            if ($isBengkel) {
                $with[] = 'vehicles';
            }
            
            $this->customerInfo = Customer::with($with)->find($value);
        } else {
            $this->customerInfo = null;
        }
        $this->appliedVoucher = null;
        $this->pointsToRedeem = 0;
        $this->claimedRewards = [];
        $this->syncAmountPaid();
    }

    public function getAvailableRewardsProperty()
    {
        return LoyaltyReward::with('product')
            ->where('company_id', filament()->getTenant()?->id)
            ->where('is_active', true)
            ->get();
    }

    public function getTotalPointsUsed()
    {
        $manualRedeem = (int) $this->pointsToRedeem;
        $rewardPoints = collect($this->claimedRewards)->sum('points');
        return $manualRedeem + $rewardPoints;
    }

    public function getRewardDiscountAmount()
    {
        return collect($this->claimedRewards)->where('type', 'discount')->sum('discount_amount');
    }

    // FUNGSI TAMBAH ITEM MANUAL
    public function addCustomItemToCart()
    {
        $price = preg_replace('/[^0-9]/', '', (string) $this->customItemPrice);
        $cost = preg_replace('/[^0-9]/', '', (string) $this->customItemCost);

        $this->customItemPrice = $price === '' ? null : $price;
        $this->customItemCost = $cost === '' ? null : $cost;

        $this->validate([
            'customItemName' => 'required|string|max:255',
            'customItemPrice' => 'required|numeric|min:0',
            'customItemCost' => 'nullable|numeric|min:0',
        ], [
            'customItemName.required' => 'Nama item wajib diisi.',
            'customItemPrice.required' => 'Harga jual wajib diisi.',
            'customItemPrice.numeric' => 'Harga jual harus berupa angka.',
        ]);

        $fakeId = 'manual_' . time() . '_' . rand(100, 999);

        $this->cart[$fakeId] = [
            'id' => null, 
            'name' => $this->customItemName,
            'item_type' => 'service', 
            'product_type' => 'standard',
            'price' => (float) $this->customItemPrice,
            'cost' => (float) ($this->customItemCost ?: 0), 
            'qty' => 1,
            'uom_id' => null,
            'uom_name' => '-',
            'conversion_factor' => 1,
            'image' => null,
            'available_uoms' => [], 
            'is_manual' => true,
        ];

        $this->syncAmountPaid();
        $this->reset(['customItemName', 'customItemCost', 'customItemPrice']);
        $this->dispatch('close-custom-item-modal');
        Notification::make()->title('Item Manual Ditambahkan!')->success()->send();
    }

    public function addToCart($productId)
    {
        // Pertama coba cari di collection saat ini (untuk performa)
        $product = collect($this->products)->firstWhere('id', $productId);
        
        // Jika tidak ada (karena dicari langsung via fungsi scan kilat), query dari DB
        if (!$product) {
            $outletId = auth()->user()->outlet_id;
            $latestStockSubquery = Stock::selectRaw('COALESCE(qty, 0)')
                ->whereColumn('product_id', 'products.id')
                ->where('outlet_id', $outletId)
                ->limit(1);

            $product = Product::query()
                ->where('products.id', $productId)
                ->leftJoin('uoms as base_uoms', 'products.base_uom_id', '=', 'base_uoms.id')
                ->leftJoin('product_uoms', function ($join) {
                    $join->on('products.id', '=', 'product_uoms.product_id')
                         ->where('product_uoms.is_default', true)
                         ->whereNull('product_uoms.deleted_at'); 
                })
                ->leftJoin('uoms as variant_uoms', 'product_uoms.uom_id', '=', 'variant_uoms.id')
                ->select([
                    'products.id', 'products.name', 'products.image_url', 'products.item_type',
                    'products.product_type', 'products.base_price as price', 'products.cost_price as cost', 
                    DB::raw('COALESCE(product_uoms.uom_id, products.base_uom_id) as uom_id'),
                    DB::raw("COALESCE(variant_uoms.name, base_uoms.name, '-') as uom_name"),
                    DB::raw('COALESCE(product_uoms.conversion_factor, 1) as conversion_factor')
                ])
                ->selectSub($latestStockSubquery, 'current_stock')
                ->first();
        }

        if (!$product) return; 

        $isService = ($product->item_type === 'service' || $product->item_type === 'bundle'); // Tambahkan Bundle sbg bypass stok
        $isBundle = in_array($product->product_type, ['bundle', 'recipe']);
        $currentStock = (float) ($product->current_stock ?? 0);

        if (!$isService && !$isBundle && $currentStock <= 0) {
            Notification::make()->title('Stok Habis!')->body("Stok untuk {$product->name} kosong di Outlet ini.")->danger()->send();
            return;
        }

        if (isset($this->cart[$productId])) {
            $nextQty = $this->cart[$productId]['qty'] + 1;
            
            if (!$isService && !$isBundle && ($nextQty * $this->cart[$productId]['conversion_factor']) > $currentStock) {
                Notification::make()->title('Stok tidak mencukupi!')->warning()->send();
                return;
            }
            
            $this->cart[$productId]['qty']++;
        } else {
            $availableUoms = [];
            $baseUomData = DB::table('products')
                ->leftJoin('uoms', 'products.base_uom_id', '=', 'uoms.id')
                ->where('products.id', $productId)
                ->select('uoms.id', 'uoms.name', 'products.base_price as price', DB::raw('1 as conversion_factor'))
                ->first();

            if ($baseUomData && $baseUomData->id) {
                $availableUoms[$baseUomData->id] = [
                    'id' => $baseUomData->id, 'name' => $baseUomData->name,
                    'price' => (float) $baseUomData->price, 'conversion_factor' => 1.00,
                ];
            }

            $variantUoms = DB::table('product_uoms')
                ->join('uoms', 'product_uoms.uom_id', '=', 'uoms.id')
                ->where('product_uoms.product_id', $productId)
                ->select('uoms.id', 'uoms.name', 'product_uoms.selling_price as price', 'product_uoms.conversion_factor')
                ->get();

            foreach ($variantUoms as $vu) {
                $availableUoms[$vu->id] = [
                    'id' => $vu->id, 'name' => $vu->name,
                    'price' => (float) $vu->price, 'conversion_factor' => (float) $vu->conversion_factor,
                ];
            }

            $this->cart[$productId] = [
                'id' => $product->id, 'name' => $product->name,
                'item_type' => $product->item_type, 'product_type' => $product->product_type,
                'price' => (float) $product->price, 'cost' => (float) $product->cost, 
                'qty' => 1, 'uom_id' => $product->uom_id ?? ($baseUomData->id ?? null), 'uom_name' => $product->uom_name ?? ($baseUomData->name ?? '-'),
                'conversion_factor' => (float) ($product->conversion_factor ?? 1),
                'image' => $product->image_url ?? 'https://placehold.co/150',
                'available_uoms' => array_values($availableUoms), 
            ];
        }
        $this->syncAmountPaid();
    }

    public function changeUom($productId, $newUomId)
    {
        if (!isset($this->cart[$productId])) return;

        $item = $this->cart[$productId];
        $selectedUom = collect($item['available_uoms'])->firstWhere('id', $newUomId);

        if ($selectedUom) {
            $currentStockRecord = Stock::where('product_id', $productId)->where('outlet_id', auth()->user()->outlet_id)->first();
            $currentStock = $currentStockRecord ? (float) $currentStockRecord->qty : 0;
            
            $isService = ($item['item_type'] ?? 'goods') === 'service';
            $isBundle = in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']);

            if (!$isService && !$isBundle && $selectedUom['conversion_factor'] > $currentStock) {
                Notification::make()->title('Stok tidak mencukupi untuk satuan ini!')->danger()->send();
                $this->dispatch('reset-uom', productId: $productId, uomId: $item['uom_id']);
                return; 
            }

            $totalBaseQty = $item['qty'] * $selectedUom['conversion_factor'];
            if (!$isService && !$isBundle && $totalBaseQty > $currentStock) {
                Notification::make()->title('Kuantitas disesuaikan batas limit stok!')->warning()->send();
                $this->cart[$productId]['qty'] = floor($currentStock / $selectedUom['conversion_factor']);
            }

            $this->cart[$productId]['uom_id'] = $selectedUom['id'];
            $this->cart[$productId]['uom_name'] = $selectedUom['name'];
            $this->cart[$productId]['price'] = $selectedUom['price'];
            $this->cart[$productId]['conversion_factor'] = $selectedUom['conversion_factor'];
            $this->syncAmountPaid();
        }
    }

    public function updateQty($productId, $delta)
    {
        if (!isset($this->cart[$productId])) return;
        
        $nextQty = $this->cart[$productId]['qty'] + $delta;

        if ($nextQty <= 0) {
            unset($this->cart[$productId]);
            $this->syncAmountPaid();
            return;
        }
        
        $isService = ($this->cart[$productId]['item_type'] ?? 'goods') === 'service';
        $isBundle = in_array($this->cart[$productId]['product_type'] ?? 'standard', ['bundle', 'recipe']);
        
        if (!$isService && !$isBundle && !isset($this->cart[$productId]['is_manual'])) {
            $currentStockRecord = Stock::where('product_id', $this->cart[$productId]['id'])->where('outlet_id', auth()->user()->outlet_id)->first();
            $currentStock = $currentStockRecord ? (float) $currentStockRecord->qty : 0;
            
            if (($nextQty * $this->cart[$productId]['conversion_factor']) > $currentStock) {
                Notification::make()->title('Stok melebihi batas mutasi!')->warning()->send();
                return;
            }
        }

        $this->cart[$productId]['qty'] = $nextQty;
        $this->syncAmountPaid();
    }

    public function checkActiveSession()
    {
        $this->activeSession = PosSession::where('user_id', auth()->id())
            ->where('outlet_id', auth()->user()->outlet_id)
            ->where('status', 'open')
            ->first();
    }

    public function openShift()
    {
        $this->validate(['openingAmount' => 'required|numeric|min:0']);

        $this->activeSession = PosSession::create([
            'company_id' => filament()->getTenant()->id,
            'outlet_id' => auth()->user()->outlet_id,
            'user_id' => auth()->id(),
            'opening_time' => now(),
            'status' => 'open',
            'opening_amount' => $this->openingAmount,
            'notes' => $this->sessionNotes,
        ]);

        Notification::make()->title('Kasir Berhasil Dibuka!')->success()->send();
    }

    public function setQty($productId, $newQty)
    {
        if (!isset($this->cart[$productId])) return;

        $newQty = (float) $newQty;

        if ($newQty <= 0) {
            unset($this->cart[$productId]);
            $this->syncAmountPaid();
            return;
        }

        $isService = ($this->cart[$productId]['item_type'] ?? 'goods') === 'service';
        $isBundle = in_array($this->cart[$productId]['product_type'] ?? 'standard', ['bundle', 'recipe']);
        
        if (!$isService && !$isBundle && !isset($this->cart[$productId]['is_manual'])) {
            $currentStockRecord = Stock::where('product_id', $this->cart[$productId]['id'])->where('outlet_id', auth()->user()->outlet_id)->first();
            $currentStock = $currentStockRecord ? (float) $currentStockRecord->qty : 0;
            
            if (($newQty * $this->cart[$productId]['conversion_factor']) > $currentStock) {
                Notification::make()->title('Sisa stok tidak mencukupi untuk jumlah ini!')->warning()->send();
                $this->cart[$productId]['qty'] = floor($currentStock / $this->cart[$productId]['conversion_factor']);
                $this->syncAmountPaid();
                return;
            }
        }

        $this->cart[$productId]['qty'] = $newQty;
        $this->syncAmountPaid();
    }

    public function claimReward($rewardId)
    {
        if (!$this->customerInfo) {
            Notification::make()->title('Pilih pelanggan dulu!')->warning()->send();
            return;
        }

        $reward = LoyaltyReward::find($rewardId);
        if (!$reward) return;

        if (isset($this->claimedRewards[$rewardId])) {
            Notification::make()->title('Hadiah ini sudah diklaim di nota ini!')->warning()->send();
            return;
        }

        if ($this->customerInfo['points_balance'] < ($this->getTotalPointsUsed() + $reward->points_required)) {
            Notification::make()->title('Sisa poin tidak cukup!')->danger()->send();
            return;
        }

        if ($reward->reward_type === 'product') {
            $product = collect($this->products)->firstWhere('id', $reward->product_id);
            if (!$product) {
                Notification::make()->title('Produk hadiah tidak tersedia!')->danger()->send();
                return;
            }

            $currentStockRecord = Stock::where('product_id', $product->id)->where('outlet_id', auth()->user()->outlet_id)->first();
            $currentStock = $currentStockRecord ? (float) $currentStockRecord->qty : 0;
            
            if ($product->item_type !== 'service' && $currentStock < 1) {
                Notification::make()->title('Stok barang hadiah habis!')->danger()->send();
                return;
            }

            $cartKey = 'reward_' . $reward->id;
            $this->cart[$cartKey] = [
                'id' => $product->id,
                'name' => '🎁 [HADIAH] ' . $product->name,
                'item_type' => $product->item_type,
                'product_type' => $product->product_type,
                'price' => 0, 
                'cost' => (float) $product->cost,
                'qty' => 1,
                'uom_id' => $product->uom_id,
                'uom_name' => $product->uom_name,
                'conversion_factor' => 1,
                'is_reward' => true,
                'reward_id' => $reward->id,
            ];
        }

        $this->claimedRewards[$reward->id] = [
            'id' => $reward->id,
            'name' => $reward->name,
            'type' => $reward->reward_type,
            'points' => $reward->points_required,
            'discount_amount' => $reward->discount_amount
        ];

        Notification::make()->title('Hadiah berhasil ditambahkan!')->success()->send();
        $this->syncAmountPaid();
        $this->dispatch('close-reward-modal');
    }

    public function removeReward($rewardId)
    {
        unset($this->claimedRewards[$rewardId]);
        $this->syncAmountPaid();
    }

    public function removeItem($key)
    {
        if (isset($this->cart[$key])) {
            if (isset($this->cart[$key]['is_reward'])) {
                unset($this->claimedRewards[$this->cart[$key]['reward_id']]);
            }
            unset($this->cart[$key]);
            $this->syncAmountPaid();
        }
    }

    public function getSubtotal() { 
        return collect($this->cart)->sum(fn ($i) => (float) $i['price'] * (float) $i['qty']); 
    }

    public function applyVoucher()
    {
        if (empty($this->voucherCode)) return;

        $tenantId = filament()->getTenant()?->id;
        $voucher = Voucher::where('code', $this->voucherCode)
            ->where('company_id', $tenantId)->where('is_active', true)->first();

        if (!$voucher) {
            Notification::make()->title('Voucher tidak ditemukan atau tidak aktif!')->warning()->send(); return;
        }
        if ($voucher->start_date && now()->isBefore($voucher->start_date)) {
            Notification::make()->title('Voucher belum berlaku!')->warning()->send(); return;
        }
        if ($voucher->end_date && now()->isAfter($voucher->end_date)) {
            Notification::make()->title('Voucher sudah kadaluarsa!')->danger()->send(); return;
        }
        if ($voucher->usage_limit && $voucher->used_count >= $voucher->usage_limit) {
            Notification::make()->title('Kuota voucher ini sudah habis!')->danger()->send(); return;
        }
        if ($this->getSubtotal() < $voucher->min_purchase) {
            Notification::make()->title('Belum memenuhi minimal belanja Rp ' . number_format($voucher->min_purchase, 0, ',', '.'))->warning()->send(); return;
        }

        $this->appliedVoucher = $voucher->toArray();
        $this->voucherCode = '';
        Notification::make()->title('Voucher Berhasil Digunakan!')->success()->send();
        $this->syncAmountPaid();
    }

    public function removeVoucher()
    {
        $this->appliedVoucher = null;
        $this->syncAmountPaid();
    }

    public function getMembershipDiscountAmount()
    {
        if (!$this->customerInfo || empty($this->customerInfo['membership'])) return 0;
        $pct = (float) $this->customerInfo['membership']['discount_percentage'];
        return $this->getSubtotal() * ($pct / 100);
    }

    public function getVoucherDiscountAmount()
    {
        if (!$this->appliedVoucher) return 0;
        $subtotalAfterMembership = $this->getSubtotal() - $this->getMembershipDiscountAmount();
        
        if ($this->appliedVoucher['discount_type'] === 'percentage') {
            $discount = $subtotalAfterMembership * ($this->appliedVoucher['discount_value'] / 100);
            if ($this->appliedVoucher['max_discount']) {
                $discount = min($discount, (float) $this->appliedVoucher['max_discount']);
            }
            return $discount;
        }
        return (float) $this->appliedVoucher['discount_value'];
    }

    public function updatedPointsToRedeem($value)
    {
        $maxManual = ($this->customerInfo['points_balance'] ?? 0) - collect($this->claimedRewards)->sum('points');
        if ($value > $maxManual) {
            $this->pointsToRedeem = $maxManual > 0 ? $maxManual : 0;
        } elseif ($value < 0 || $value == '') {
            $this->pointsToRedeem = 0;
        }
        $this->syncAmountPaid();
    }

    public function getPointDiscountAmount()
    {
        if (!$this->customerInfo || $this->pointsToRedeem <= 0) return 0;
        
        $company = filament()->getTenant();
        
        $pointValue = (float) ($company->loyalty_point_value ?? 1); 
        
        return $this->pointsToRedeem * $pointValue;
    }

    public function getGrandTotal()
    {
        $subtotal = $this->getSubtotal();
        $totalDiscount = $this->getMembershipDiscountAmount() 
                       + $this->getVoucherDiscountAmount() 
                       + (float) ($this->discount ?: 0) 
                       + $this->getPointDiscountAmount() 
                       + $this->getRewardDiscountAmount();

        $grandTotal = $subtotal - $totalDiscount;
        return $grandTotal > 0 ? $grandTotal : 0;
    }

    public function getChangeAmount() { return max(0, (float)$this->amountPaid - $this->getGrandTotal()); }

    public function syncAmountPaid() { $this->amountPaid = $this->getGrandTotal(); }
    public function updatedDiscount() { $this->syncAmountPaid(); }
    public function updatedPaymentMethod() { $this->accountId = null; }

    public function getAvailableAccountsProperty()
    {
        if (!$this->paymentMethod) return [];
        return Account::where('company_id', filament()->getTenant()->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('outlet_id', auth()->user()->outlet_id)->orWhereNull('outlet_id');
            })
            ->whereJsonContains('payment_methods', $this->paymentMethod)
            ->get();
    }

    public function getHasAvailableVouchersProperty()
    {
        return Voucher::where('company_id', filament()->getTenant()->id)
            ->where('is_active', true)
            ->exists();
    }

    // =========================================================================
    // SUBMIT TRANSACTION
    // =========================================================================
    public function submitTransaction()
    {
        if (empty($this->cart)) {
            Notification::make()->title('Keranjang kosong!')->danger()->send(); return;
        }
        if (empty($this->paymentMethod)) {
            Notification::make()->title('Pilih metode pembayaran terlebih dahulu!')->warning()->send(); return;
        }
        if (empty($this->accountId)) {
            Notification::make()->title('Pilih rekening tujuan penerimaan uang!')->warning()->send(); return;
        }

        $outletId = auth()->user()->outlet_id;
        $company = filament()->getTenant();
        $companyId = $company?->id;

        try {
            DB::beginTransaction();

            $requiredStocks = []; 
            foreach ($this->cart as $item) {
                $isBundle = in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']);
                $isService = ($item['item_type'] ?? 'goods') === 'service';

                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $item['id'])->get();
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        
                        if ($child && $child->item_type === 'goods') {
                            $compFactor = 1;
                            if (!empty($comp->uom_id)) {
                                $uomPivot = DB::table('product_uoms')
                                    ->where('product_id', $comp->child_product_id)
                                    ->where('uom_id', $comp->uom_id)
                                    ->whereNull('deleted_at')
                                    ->first();
                                if ($uomPivot) {
                                    $compFactor = (float) $uomPivot->conversion_factor;
                                }
                            }

                            // Rumus: Qty Beli x Konversi Beli x (Qty Komponen x Konversi Komponen)
                            $qtyNeeded = $item['qty'] * $item['conversion_factor'] * ((float)$comp->quantity * $compFactor);
                            $requiredStocks[$child->id] = ($requiredStocks[$child->id] ?? 0) + $qtyNeeded;
                        }
                    }
                } elseif (!$isService && empty($item['is_reward']) && empty($item['is_manual'])) {
                    $qtyNeeded = $item['qty'] * $item['conversion_factor'];
                    $requiredStocks[$item['id']] = ($requiredStocks[$item['id']] ?? 0) + $qtyNeeded;
                }
            }

            if (!empty($requiredStocks)) {
                $productIdsToLock = array_keys($requiredStocks);
                sort($productIdsToLock);

                $lockedProducts = Product::whereIn('id', $productIdsToLock)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($requiredStocks as $prodId => $totalNeeded) {
                    $stockRecord = DB::table('stocks')
                        ->where('product_id', $prodId)
                        ->where('outlet_id', $outletId)
                        ->first();
                        
                    $available = $stockRecord ? (float)$stockRecord->qty : 0;
                    
                    if ($totalNeeded > $available) {
                        $prodName = $lockedProducts[$prodId]->name ?? 'Produk';
                        DB::rollBack(); 
                        
                        Notification::make()
                            ->title("Stok komponen kurang!")
                            ->body("Dibutuhkan {$totalNeeded} pcs untuk {$prodName}. Hanya tersedia {$available} pcs.")
                            ->danger()
                            ->send();
                            
                        $this->dispatch('close-payment-modal');
                        return; 
                    }
                }
            }

            $isMidtransPayment = ($this->paymentMethod === 'qris' || $this->paymentMethod === 'ewallet') && !empty($company->midtrans_server_key);
            $uniqueOrderId = 'POS-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

            if ($isMidtransPayment) {
                $rawGrandTotal = $this->getGrandTotal();
                $cleanGrandTotal = (int) preg_replace('/[^0-9]/', '', (string) round($rawGrandTotal));

                if ($cleanGrandTotal < 1) {
                    DB::rollBack();
                    Notification::make()->title('Gagal Transaksi QRIS')->body('Nominal minimal Midtrans adalah Rp 1.')->danger()->send();
                    return;
                }

                $itemDetails = [];
                foreach ($this->cart as $item) {
                    $itemDetails[] = [
                        'id' => empty($item['id']) ? 'MANUAL' : (string) $item['id'],
                        'price' => (int) preg_replace('/[^0-9]/', '', (string) round($item['price'])),
                        'quantity' => (int) $item['qty'], 
                        'name' => substr($item['name'], 0, 50),
                    ];
                }

                $totalDiscount = $this->getMembershipDiscountAmount() + $this->getVoucherDiscountAmount() + (float)($this->discount ?: 0) + $this->getPointDiscountAmount() + $this->getRewardDiscountAmount();

                if ($totalDiscount > 0) {
                    $itemDetails[] = [
                        'id' => 'DISCOUNT', 'price' => -1 * (int) preg_replace('/[^0-9]/', '', (string) round($totalDiscount)),
                        'quantity' => 1, 'name' => 'Total Diskon Nota',
                    ];
                }

                $transaction = Transaction::create([
                    'company_id' => $companyId, 'outlet_id' => $outletId, 'user_id' => auth()->id(),
                    'pos_session_id' => ($company->hasFeature('finance.closing_shift') && $this->activeSession) ? $this->activeSession->id : null,
                    'customer_id' => $this->customerId ?: null, 'account_id' => $this->accountId, 
                    'transaction_number' => $uniqueOrderId, 'type' => 'sale', 'in_out' => 'in', 'status' => 'pending',
                    'payment_method' => $this->paymentMethod, 'subtotal' => $this->getSubtotal(),
                    'discount' => $this->discount ?: 0, 'points_used' => $this->getTotalPointsUsed(),
                    'point_discount_amount' => $this->getPointDiscountAmount() + $this->getRewardDiscountAmount(),
                    'grand_total' => $cleanGrandTotal, 'amount_paid' => $cleanGrandTotal, 'amount_change' => 0,
                ]);

                foreach ($this->cart as $item) {
                    TransactionItem::create([
                        'company_id' => $companyId, 'transaction_id' => $transaction->id,
                        'product_id' => $item['id'] ?? null, 
                        'item_name' => $item['name'], 
                        'uom_id' => $item['uom_id'] ?? null, 
                        'qty' => $item['qty'],
                        'conversion_factor' => $item['conversion_factor'], 'base_qty' => $item['qty'] * $item['conversion_factor'],
                        'cost_price' => ($item['cost'] ?? 0) * $item['conversion_factor'], 
                        'selling_price' => $item['price'], 'subtotal' => $item['price'] * $item['qty'],
                    ]);
                }

                try {
                    $snapToken = MidtransService::createTransaction($company, ['order_id' => $uniqueOrderId, 'gross_amount' => $cleanGrandTotal], $itemDetails);
                    
                    DB::commit(); 
                    
                    $this->dispatch('close-payment-modal');
                    $this->dispatch('trigger-midtrans-snap', snapToken: $snapToken, transactionId: $transaction->id);
                    return;
                } catch (\Exception $e) {
                    DB::rollBack(); 
                    Notification::make()->title('Gagal terhubung ke Midtrans')->body($e->getMessage())->danger()->send(); 
                    return;
                }
            }

            $newTrx = Transaction::create([
                'company_id' => $companyId, 'outlet_id' => $outletId, 'user_id' => auth()->id(),
                'pos_session_id' => (filament()->getTenant()->hasFeature('finance.closing_shift') && $this->activeSession) ? $this->activeSession->id : null,
                'customer_id' => $this->customerId ?: null, 'account_id' => $this->accountId, 
                'transaction_number' => 'SALE-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'type' => 'sale', 'in_out' => 'in', 'status' => 'completed',
                'payment_method' => $this->paymentMethod, 'subtotal' => $this->getSubtotal(),
                'discount' => $this->discount ?: 0, 'points_used' => $this->getTotalPointsUsed(),
                'point_discount_amount' => $this->getPointDiscountAmount() + $this->getRewardDiscountAmount(),
                'grand_total' => $this->getGrandTotal(), 'amount_paid' => $this->amountPaid ?: $this->getGrandTotal(),
                'amount_change' => $this->getChangeAmount(),
            ]);

            foreach ($this->cart as $item) {
                TransactionItem::create([
                    'company_id' => $companyId, 'transaction_id' => $newTrx->id,
                    'product_id' => $item['id'] ?? null, 
                    'item_name' => $item['name'], 
                    'uom_id' => $item['uom_id'] ?? null, 
                    'qty' => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'], 'base_qty' => $item['qty'] * $item['conversion_factor'],
                    'cost_price' => ($item['cost'] ?? 0) * $item['conversion_factor'], 
                    'selling_price' => $item['price'], 'subtotal' => $item['price'] * $item['qty'],
                ]);
            }

            $this->fulfillTransaction($newTrx);
            
            DB::commit(); 

            Notification::make()->title('Transaksi Berhasil!')->success()->send();
            $this->dispatch('open-receipt', url: route('pos.receipt', $newTrx->id));
            $this->dispatch('close-payment-modal'); 
            $this->reset(['cart', 'discount', 'amountPaid', 'customerSearch', 'customerId', 'customerInfo', 'voucherCode', 'appliedVoucher', 'pointsToRedeem', 'paymentMethod', 'accountId', 'claimedRewards', 'customItemName', 'customItemPrice', 'customItemCost']);

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Terjadi Kesalahan')->body($e->getMessage())->danger()->send();
        }
    }

    public function processPaymentSuccess($transactionId)
    {
        $transaction = Transaction::find($transactionId);
        if ($transaction && $transaction->status === 'pending') {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'completed']);
                $this->fulfillTransaction($transaction);
            });

            Notification::make()->title('Pembayaran QRIS Berhasil!')->success()->send();
            $this->dispatch('open-receipt', url: route('pos.receipt', $transaction->id));
            $this->reset(['cart', 'discount', 'amountPaid', 'customerSearch', 'customerId', 'customerInfo', 'voucherCode', 'appliedVoucher', 'pointsToRedeem', 'paymentMethod', 'accountId', 'claimedRewards', 'customItemName', 'customItemPrice', 'customItemCost']);
        }
    }

    protected function fulfillTransaction(Transaction $transaction)
    {
        $outletId = $transaction->outlet_id;
        $companyId = $transaction->company_id;

        $adminFee = 0;
        $gross = (float) $transaction->grand_total;
        $method = $transaction->payment_method;

        if (in_array($method, ['qris', 'ewallet'])) {
            $adminFee = $gross * 0.007;
        } elseif ($method === 'transfer') {
            $adminFee = 4000;
        } elseif ($method === 'credit_card') {
            $adminFee = ($gross * 0.02) + 2000;
        }

        $adminFee = round($adminFee);
        $netAmount = $gross - $adminFee; 

        if ($adminFee > 0) {
            DB::table('transactions')->where('id', $transaction->id)->update(['admin_fee' => $adminFee]);
        }

        Account::where('id', $transaction->account_id)->increment('balance', $netAmount);

        if ($this->activeSession) {
            $this->activeSession->increment('total_sales', $transaction->grand_total);
            if ($method === 'cash') {
                $this->activeSession->increment('total_cash_sales', $transaction->grand_total);
            }
        }

        if ($this->appliedVoucher) {
            Voucher::where('id', $this->appliedVoucher['id'])->increment('used_count');
        }

        $company = filament()->getTenant();

        if ($this->pointsToRedeem > 0 && $transaction->customer_id) {
            PointHistory::create([
                'company_id' => $companyId, 'customer_id' => $transaction->customer_id,
                'type' => 'redeem', 'amount' => $this->pointsToRedeem,
                'reference_id' => $transaction->transaction_number, 'description' => 'Tukar poin (Cashback) di Kasir',
            ]);
        }

        if (!empty($this->claimedRewards) && $transaction->customer_id) {
            foreach ($this->claimedRewards as $reward) {
                PointHistory::create([
                    'company_id' => $companyId, 'customer_id' => $transaction->customer_id,
                    'type' => 'redeem', 'amount' => $reward['points'],
                    'reference_id' => $transaction->transaction_number, 'description' => 'Tukar Hadiah: ' . $reward['name'],
                ]);
            }
        }

        $hasCrm = data_get($company?->subscriptionPlan?->features, 'crm.membership') === true;

        if ($transaction->customer_id && $hasCrm && $company->loyalty_spend_amount > 0) {
            $earnedMultiplier = floor($transaction->grand_total / $company->loyalty_spend_amount);
            $earnedPoints = $earnedMultiplier * (int) $company->loyalty_point_earned; 

            if ($earnedPoints > 0) {
                PointHistory::create([
                    'company_id' => $companyId, 'customer_id' => $transaction->customer_id,
                    'type' => 'earn', 'amount' => $earnedPoints,
                    'reference_id' => $transaction->transaction_number, 'description' => 'Earned points from POS Sale',
                ]);
            }
        }

        $items = TransactionItem::where('transaction_id', $transaction->id)->get();
        foreach ($items as $trxItem) {
            if (!$trxItem->product_id) continue;

            $product = Product::find($trxItem->product_id);
            if (!$product) continue;

            $isService = ($product->item_type === 'service');
            $isBundle = in_array($product->product_type, ['bundle', 'recipe']);

            if ($isBundle) {
                $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                foreach ($components as $comp) {
                    $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                    if ($child && $child->item_type === 'goods') {
                        $compFactor = 1;
                        if (!empty($comp->uom_id)) {
                            $uomPivot = DB::table('product_uoms')
                                ->where('product_id', $comp->child_product_id)
                                ->where('uom_id', $comp->uom_id)
                                ->whereNull('deleted_at')
                                ->first();
                            if ($uomPivot) {
                                $compFactor = (float) $uomPivot->conversion_factor;
                            }
                        }

                        // Qty Dibeli * Konversi UoM Beli * (Qty Bahan * Konversi UoM Bahan)
                        $qtyToDeduct = $trxItem->base_qty * ((float)$comp->quantity * $compFactor);
                        
                        $stockRecord = Stock::firstOrCreate(
                            ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $comp->child_product_id],
                            ['qty' => 0]
                        );
                        $stockRecord->lockForUpdate();
                        
                        $balanceBefore = (float) $stockRecord->qty;
                        $balanceAfter = $balanceBefore - $qtyToDeduct;
                        
                        $stockRecord->update(['qty' => $balanceAfter]);

                        StockMovement::create([
                            'company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $comp->child_product_id,
                            'type' => 'sale', 'reference_type' => Transaction::class, 'reference_id' => $transaction->id,
                            'quantity' => $qtyToDeduct, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                            'remarks' => "Terjual dalam Paket/Bundle. Nota: " . $transaction->transaction_number,
                        ]);
                    }
                }
            } elseif (!$isService) {
                
                $stockRecord = Stock::firstOrCreate(
                    ['company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id],
                    ['qty' => 0]
                );
                $stockRecord->lockForUpdate();
                
                $balanceBefore = (float) $stockRecord->qty;
                $balanceAfter = $balanceBefore - $trxItem->base_qty;
                
                $stockRecord->update(['qty' => $balanceAfter]);

                StockMovement::create([
                    'company_id' => $companyId, 'outlet_id' => $outletId, 'product_id' => $product->id,
                    'type' => 'sale', 'reference_type' => Transaction::class, 'reference_id' => $transaction->id,
                    'quantity' => $trxItem->base_qty, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                    'remarks' => 'Penjualan POS Nota: ' . $transaction->transaction_number,
                ]);
            }
        }
    }
}
?>
