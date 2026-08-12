<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Stock; // <-- IMPORT MODEL STOCK BARU KITA
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

class PosCashier extends Page
{
    protected string $view = 'filament.tenant.pages.pos-cashier';
    protected static ?string $slug = 'pos/cashier';

    public function getTitle(): string|Htmlable { return 'Kasir POS'; }
    public static function getNavigationLabel(): string { return 'Penjualan (POS)'; }
    public static function getNavigationIcon(): string|BackedEnum|null { return 'heroicon-o-shopping-cart'; }

    // Livewire States
    public $search = '';
    public $activeCategory = 'all';
    public $cart = [];
    public $discount = 0;
    public $amountPaid = 0;
    public $pointsToRedeem = 0;
    
    // PROPERTI CRM & PEMBAYARAN
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
    
    public function getCategoriesProperty()
    {
        return Category::where('company_id', filament()->getTenant()?->id)->get();
    }

    public function getProductsProperty()
    {
        $user = auth()->user();
        $tenantId = filament()->getTenant()?->id;
        $outletId = $user->outlet_id;

        // PERBAIKAN STOK: Membaca langsung dari tabel stocks (Lebih Ringan)
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
            ->join('uoms as base_uoms', 'products.base_uom_id', '=', 'base_uoms.id')
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
                DB::raw('COALESCE(product_uoms.uom_id, products.base_uom_id) as uom_id'),
                DB::raw('COALESCE(variant_uoms.name, base_uoms.name) as uom_name'),
                DB::raw('COALESCE(product_uoms.conversion_factor, 1) as conversion_factor')
            ])
            ->selectSub($latestStockSubquery, 'current_stock')
            ->when($this->search, function ($q) {
                $q->where(function($query) {
                    $query->where('products.name', 'like', "%{$this->search}%")
                          ->orWhere('products.barcode', 'like', "%{$this->search}%")
                          ->orWhere('product_uoms.barcode', 'like', "%{$this->search}%");
                });
            })
            ->when($this->activeCategory !== 'all', fn ($q) => $q->where('products.category_id', $this->activeCategory))
            ->get();
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $this->customerInfo = Customer::with('membership')->find($value);
        } else {
            $this->customerInfo = null;
        }
        $this->appliedVoucher = null;
        $this->pointsToRedeem = 0;
        $this->claimedRewards = [];
        $this->syncAmountPaid();
    }
    
    // PERBAIKAN FILTER PELANGGAN BERDASARKAN OUTLET
    public function getCustomersProperty()
    {
        $tenantId = filament()->getTenant()?->id;
        $user = auth()->user();
        $outletId = $user->outlet_id;

        return Customer::where('company_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($outletId) {
                // Tampilkan pelanggan yang outlet_id nya kosong (Global)
                // ATAU pelanggan yang terdaftar khusus di cabang kasir ini
                $query->whereNull('outlet_id')
                      ->orWhere('outlet_id', $outletId);
            })
            ->get();
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

    public function addToCart($productId)
    {
        $product = collect($this->products)->firstWhere('id', $productId);
        $isService = ($product->item_type === 'service');
        $isBundle = in_array($product->product_type, ['bundle', 'recipe']);
        $currentStock = (float) ($product->current_stock ?? 0);

        if (!$isService && !$isBundle && (!$product || $currentStock <= 0)) {
            Notification::make()->title('Stok Produk Habis di Outlet Ini!')->danger()->send();
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
                ->join('uoms', 'products.base_uom_id', '=', 'uoms.id')
                ->where('products.id', $productId)
                ->select('uoms.id', 'uoms.name', 'products.base_price as price', DB::raw('1 as conversion_factor'))
                ->first();

            if ($baseUomData) {
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
                'qty' => 1, 'uom_id' => $product->uom_id, 'uom_name' => $product->uom_name,
                'conversion_factor' => (float) $product->conversion_factor,
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
            $product = collect($this->products)->firstWhere('id', $productId);
            $currentStock = (float) ($product->current_stock ?? 0);
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
        
        $product = collect($this->products)->firstWhere('id', $productId);
        $nextQty = $this->cart[$productId]['qty'] + $delta;

        if ($nextQty <= 0) {
            unset($this->cart[$productId]);
            $this->syncAmountPaid();
            return;
        }
        
        $isService = ($this->cart[$productId]['item_type'] ?? 'goods') === 'service';
        $isBundle = in_array($this->cart[$productId]['product_type'] ?? 'standard', ['bundle', 'recipe']);
        
        if (!$isService && !$isBundle && ($nextQty * $this->cart[$productId]['conversion_factor']) > (float)($product->current_stock ?? 0)) {
            Notification::make()->title('Stok melebihi batas mutasi!')->warning()->send();
            return;
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
        $product = collect($this->products)->firstWhere('id', $productId);

        if ($newQty <= 0) {
            unset($this->cart[$productId]);
            $this->syncAmountPaid();
            return;
        }

        $isService = ($this->cart[$productId]['item_type'] ?? 'goods') === 'service';
        $isBundle = in_array($this->cart[$productId]['product_type'] ?? 'standard', ['bundle', 'recipe']);
        
        if (!$isService && !$isBundle && ($newQty * $this->cart[$productId]['conversion_factor']) > (float)($product->current_stock ?? 0)) {
            Notification::make()->title('Sisa stok tidak mencukupi untuk jumlah ini!')->warning()->send();
            $this->cart[$productId]['qty'] = floor((float)($product->current_stock ?? 0) / $this->cart[$productId]['conversion_factor']);
            $this->syncAmountPaid();
            return;
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

            $currentStock = (float) ($product->current_stock ?? 0);
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
        if (!$company->is_loyalty_enabled) return 0;
        return $this->pointsToRedeem * (float) $company->loyalty_point_value;
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

    // =========================================================================
    // UPDATE SUBMIT TRANSACTION DENGAN RACE CONDITION HANDLING
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
            // [!!! PENTING !!!] Buka transaksi DATABASE di AWAL sebelum mengecek stok
            DB::beginTransaction();

            // --- 1. HITUNG KEBUTUHAN STOK ---
            $requiredStocks = []; 
            foreach ($this->cart as $item) {
                $isBundle = in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']);
                $isService = ($item['item_type'] ?? 'goods') === 'service';

                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $item['id'])->get();
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        if ($child && $child->item_type === 'goods') {
                            $qtyNeeded = $item['qty'] * $item['conversion_factor'] * (float)$comp->quantity;
                            $requiredStocks[$child->id] = ($requiredStocks[$child->id] ?? 0) + $qtyNeeded;
                        }
                    }
                } elseif (!$isService && empty($item['is_reward'])) {
                    $qtyNeeded = $item['qty'] * $item['conversion_factor'];
                    $requiredStocks[$item['id']] = ($requiredStocks[$item['id']] ?? 0) + $qtyNeeded;
                }
            }

            // --- 2. PESSIMISTIC LOCKING PADA PRODUCT UNTUK MENCEGAH DEADLOCK ANTAR TRANSAKSI ---
            if (!empty($requiredStocks)) {
                $productIdsToLock = array_keys($requiredStocks);
                
                // MENGURUTKAN ID SANGAT PENTING: Mencegah 'Deadlock' (Error MySQL)
                sort($productIdsToLock);

                // Eksekusi Kunci! (Kasir lain akan 'ngantre/loading' sampai transaksi ini selesai)
                $lockedProducts = Product::whereIn('id', $productIdsToLock)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // --- 3. VALIDASI STOK (Membaca langsung dari tabel stocks) ---
                foreach ($requiredStocks as $prodId => $totalNeeded) {
                    $stockRecord = DB::table('stocks')
                        ->where('product_id', $prodId)
                        ->where('outlet_id', $outletId)
                        ->first();
                        
                    $available = $stockRecord ? (float)$stockRecord->qty : 0;
                    
                    if ($totalNeeded > $available) {
                        $prodName = $lockedProducts[$prodId]->name ?? 'Produk';
                        DB::rollBack(); // Buka Kunci
                        
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

            // --- 4. JIKA STOK CUKUP, BUAT TRANSAKSI ---
            $isMidtransPayment = ($this->paymentMethod === 'qris' || $this->paymentMethod === 'ewallet') && !empty($company->midtrans_server_key);
            $uniqueOrderId = 'POS-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

            // (Khusus Midtrans)
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
                        'id' => (string) $item['id'], 'price' => (int) preg_replace('/[^0-9]/', '', (string) round($item['price'])),
                        'quantity' => (int) $item['qty'], 'name' => substr($item['name'], 0, 50),
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
                        'product_id' => $item['id'], 'uom_id' => $item['uom_id'], 'qty' => $item['qty'],
                        'conversion_factor' => $item['conversion_factor'], 'base_qty' => $item['qty'] * $item['conversion_factor'],
                        'cost_price' => ($item['cost'] ?? 0) * $item['conversion_factor'], 
                        'selling_price' => $item['price'], 'subtotal' => $item['price'] * $item['qty'],
                    ]);
                }

                try {
                    $snapToken = MidtransService::createTransaction($company, ['order_id' => $uniqueOrderId, 'gross_amount' => $cleanGrandTotal], $itemDetails);
                    
                    DB::commit(); // SELESAI MIDTRANS, LEPAS KUNCI
                    
                    $this->dispatch('close-payment-modal');
                    $this->dispatch('trigger-midtrans-snap', snapToken: $snapToken, transactionId: $transaction->id);
                    return;
                } catch (\Exception $e) {
                    DB::rollBack(); // MIDTRANS GAGAL, BATALKAN SEMUA
                    Notification::make()->title('Gagal terhubung ke Midtrans')->body($e->getMessage())->danger()->send(); 
                    return;
                }
            }

            // (Pembayaran Manual/Cash)
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
                    'product_id' => $item['id'], 'uom_id' => $item['uom_id'], 'qty' => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'], 'base_qty' => $item['qty'] * $item['conversion_factor'],
                    'cost_price' => ($item['cost'] ?? 0) * $item['conversion_factor'], 
                    'selling_price' => $item['price'], 'subtotal' => $item['price'] * $item['qty'],
                ]);
            }

            // Eksekusi potong stok, jurnal dll
            $this->fulfillTransaction($newTrx);
            
            DB::commit(); // SELESAI CASH, LEPAS KUNCI (UNLOCKED)

            Notification::make()->title('Transaksi Berhasil!')->success()->send();
            $this->dispatch('open-receipt', url: route('pos.receipt', $newTrx->id));
            $this->dispatch('close-payment-modal'); 
            $this->reset(['cart', 'discount', 'amountPaid', 'customerId', 'customerInfo', 'voucherCode', 'appliedVoucher', 'pointsToRedeem', 'paymentMethod', 'accountId', 'claimedRewards']);

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
            $this->reset(['cart', 'discount', 'amountPaid', 'customerId', 'customerInfo', 'voucherCode', 'appliedVoucher', 'pointsToRedeem', 'paymentMethod', 'accountId', 'claimedRewards']);
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

        // POTONG POIN CASHBACK MANUAL
        if ($this->pointsToRedeem > 0 && $transaction->customer_id) {
            PointHistory::create([
                'company_id' => $companyId, 'customer_id' => $transaction->customer_id,
                'type' => 'redeem', 'amount' => $this->pointsToRedeem,
                'reference_id' => $transaction->transaction_number, 'description' => 'Tukar poin (Cashback) di Kasir',
            ]);
        }

        // POTONG POIN REWARD KATALOG
        if (!empty($this->claimedRewards) && $transaction->customer_id) {
            foreach ($this->claimedRewards as $reward) {
                PointHistory::create([
                    'company_id' => $companyId, 'customer_id' => $transaction->customer_id,
                    'type' => 'redeem', 'amount' => $reward['points'],
                    'reference_id' => $transaction->transaction_number, 'description' => 'Tukar Hadiah: ' . $reward['name'],
                ]);
            }
        }

        // DAPAT POIN DARI TRANSAKSI
        if ($transaction->customer_id && $company->is_loyalty_enabled && $company->loyalty_spend_amount > 0) {
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
            $product = Product::find($trxItem->product_id);
            if (!$product) continue;

            $isService = ($product->item_type === 'service');
            $isBundle = in_array($product->product_type, ['bundle', 'recipe']);

            if ($isBundle) {
                $components = DB::table('product_components')->where('parent_product_id', $product->id)->get();
                foreach ($components as $comp) {
                    $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                    if ($child && $child->item_type === 'goods') {
                        $qtyToDeduct = $trxItem->base_qty * (float)$comp->quantity;
                        
                        // PEMOTONGAN STOK BUNDLE DENGAN TABEL STOCKS + LOCKING
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
                
                // PEMOTONGAN STOK STANDAR DENGAN TABEL STOCKS + LOCKING
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