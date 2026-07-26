<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
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
    
    // PROPERTI CRM
    public $customerInfo = null; 
    public $voucherCode = ''; 
    public $appliedVoucher = null;
    public $activeSession = null;
    public $openingAmount = 0;
    public $sessionNotes = '';
    public $paymentMethod = '';
    public $accountId = null;

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
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $tenantId = filament()->getTenant()?->id;
        $outletId = $user->outlet_id;

        $latestStockSubquery = \App\Models\StockMovement::select('balance_after')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $outletId)
            ->latest('created_at')
            ->limit(1);

        return \App\Models\Product::query()
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
                'products.id',
                'products.name',
                'products.image_url',
                'products.item_type',
                'products.product_type',
                'products.base_price as price',
                'products.cost_price as cost', 
                
                \Illuminate\Support\Facades\DB::raw('COALESCE(product_uoms.barcode, products.barcode) as barcode'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(product_uoms.uom_id, products.base_uom_id) as uom_id'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(variant_uoms.name, base_uoms.name) as uom_name'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(product_uoms.conversion_factor, 1) as conversion_factor')
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
            $this->customerInfo = \App\Models\Customer::with('membership')->find($value);
        } else {
            $this->customerInfo = null;
        }
        $this->appliedVoucher = null;
        $this->pointsToRedeem = 0;
        $this->syncAmountPaid();
    }
    
    public $customerId = null;
    public function getCustomersProperty()
    {
        return \App\Models\Customer::where('company_id', filament()->getTenant()?->id)->get();
    }

    public function addToCart($productId)
    {
        $product = collect($this->products)->firstWhere('id', $productId);
        $isService = ($product->item_type === 'service');
        $isBundle = in_array($product->product_type, ['bundle', 'recipe']);
        $currentStock = (float) ($product->current_stock ?? 0);

        if (!$isService && !$isBundle && (!$product || $currentStock <= 0)) {
            \Filament\Notifications\Notification::make()->title('Stok Produk Habis di Outlet Ini!')->danger()->send();
            return;
        }

        if (isset($this->cart[$productId])) {
            $nextQty = $this->cart[$productId]['qty'] + 1;
            
            if (!$isService && !$isBundle && ($nextQty * $this->cart[$productId]['conversion_factor']) > $currentStock) {
                \Filament\Notifications\Notification::make()->title('Stok tidak mencukupi!')->warning()->send();
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
                    'id' => $baseUomData->id,
                    'name' => $baseUomData->name,
                    'price' => (float) $baseUomData->price,
                    'conversion_factor' => 1.00,
                ];
            }

            $variantUoms = DB::table('product_uoms')
                ->join('uoms', 'product_uoms.uom_id', '=', 'uoms.id')
                ->where('product_uoms.product_id', $productId)
                ->select('uoms.id', 'uoms.name', 'product_uoms.selling_price as price', 'product_uoms.conversion_factor')
                ->get();

            foreach ($variantUoms as $vu) {
                $availableUoms[$vu->id] = [
                    'id' => $vu->id,
                    'name' => $vu->name,
                    'price' => (float) $vu->price,
                    'conversion_factor' => (float) $vu->conversion_factor,
                ];
            }

            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'item_type' => $product->item_type,
                'product_type' => $product->product_type,
                'price' => (float) $product->price,
                'cost' => (float) $product->cost, 
                'qty' => 1,
                'uom_id' => $product->uom_id, 
                'uom_name' => $product->uom_name,
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
                \Filament\Notifications\Notification::make()
                    ->title('Stok tidak mencukupi untuk satuan ini!')
                    ->danger()
                    ->send();

                $this->dispatch('reset-uom', productId: $productId, uomId: $item['uom_id']);
                return; 
            }

            $totalBaseQty = $item['qty'] * $selectedUom['conversion_factor'];
            if (!$isService && !$isBundle && $totalBaseQty > $currentStock) {
                \Filament\Notifications\Notification::make()
                    ->title('Kuantitas disesuaikan batas limit stok!')
                    ->warning()
                    ->send();
                
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
        $this->activeSession = \App\Models\PosSession::where('user_id', auth()->id())
            ->where('outlet_id', auth()->user()->outlet_id)
            ->where('status', 'open')
            ->first();
    }

    public function openShift()
    {
        $this->validate([
            'openingAmount' => 'required|numeric|min:0',
        ]);

        $this->activeSession = \App\Models\PosSession::create([
            'company_id' => filament()->getTenant()->id,
            'outlet_id' => auth()->user()->outlet_id,
            'user_id' => auth()->id(),
            'opening_time' => now(),
            'status' => 'open',
            'opening_amount' => $this->openingAmount,
            'notes' => $this->sessionNotes,
        ]);

        \Filament\Notifications\Notification::make()->title('Kasir Berhasil Dibuka!')->success()->send();
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
            $this->cart[$productId]['qty'] = (float)($product->current_stock ?? 0) / $this->cart[$productId]['conversion_factor'];
            $this->syncAmountPaid();
            return;
        }

        $this->cart[$productId]['qty'] = $newQty;
        $this->syncAmountPaid();
    }

    public function removeItem($productId)
    {
        if (isset($this->cart[$productId])) {
            unset($this->cart[$productId]);
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
        $voucher = \App\Models\Voucher::where('code', $this->voucherCode)
            ->where('company_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$voucher) {
            \Filament\Notifications\Notification::make()->title('Voucher tidak ditemukan atau tidak aktif!')->warning()->send();
            return;
        }
        if ($voucher->start_date && now()->isBefore($voucher->start_date)) {
            \Filament\Notifications\Notification::make()->title('Voucher belum berlaku!')->warning()->send();
            return;
        }
        if ($voucher->end_date && now()->isAfter($voucher->end_date)) {
            \Filament\Notifications\Notification::make()->title('Voucher sudah kadaluarsa!')->danger()->send();
            return;
        }
        if ($voucher->usage_limit && $voucher->used_count >= $voucher->usage_limit) {
            \Filament\Notifications\Notification::make()->title('Kuota voucher ini sudah habis!')->danger()->send();
            return;
        }
        if ($this->getSubtotal() < $voucher->min_purchase) {
            \Filament\Notifications\Notification::make()->title('Belum memenuhi minimal belanja Rp ' . number_format($voucher->min_purchase, 0, ',', '.'))->warning()->send();
            return;
        }

        $this->appliedVoucher = $voucher->toArray();
        $this->voucherCode = '';
        \Filament\Notifications\Notification::make()->title('Voucher Berhasil Digunakan!')->success()->send();
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
        $maxPoints = $this->customerInfo['points_balance'] ?? 0;
        if ($value > $maxPoints) {
            $this->pointsToRedeem = $maxPoints;
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
        $memberDiscount = $this->getMembershipDiscountAmount();
        $voucherDiscount = $this->getVoucherDiscountAmount();
        $manualDiscount = (float) ($this->discount ?: 0);
        $pointDiscount = $this->getPointDiscountAmount();

        $totalDiscount = $memberDiscount + $voucherDiscount + $manualDiscount + $pointDiscount;
        $grandTotal = $subtotal - $totalDiscount;

        return $grandTotal > 0 ? $grandTotal : 0;
    }

    public function getChangeAmount() { return max(0, (float)$this->amountPaid - $this->getGrandTotal()); }

    public function syncAmountPaid()
    {
        $this->amountPaid = $this->getGrandTotal();
    }

    public function updatedDiscount()
    {
        $this->syncAmountPaid();
    }

    public function updatedPaymentMethod()
    {
        $this->accountId = null;
    }

    public function getAvailableAccountsProperty()
    {
        if (!$this->paymentMethod) return [];

        return \App\Models\Account::where('company_id', filament()->getTenant()->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('outlet_id', auth()->user()->outlet_id)
                  ->orWhereNull('outlet_id');
            })
            ->whereJsonContains('payment_methods', $this->paymentMethod)
            ->get();
    }

    public function submitTransaction()
    {
        if (empty($this->cart)) {
            \Filament\Notifications\Notification::make()->title('Keranjang kosong!')->danger()->send();
            return;
        }
        
        if (empty($this->paymentMethod)) {
            \Filament\Notifications\Notification::make()->title('Pilih metode pembayaran terlebih dahulu!')->warning()->send();
            return;
        }

        if (empty($this->accountId)) {
            \Filament\Notifications\Notification::make()->title('Pilih rekening tujuan penerimaan uang!')->warning()->send();
            return;
        }

        $outletId = auth()->user()->outlet_id;
        $companyId = filament()->getTenant()?->id;

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
            } elseif (!$isService) {
                $qtyNeeded = $item['qty'] * $item['conversion_factor'];
                $requiredStocks[$item['id']] = ($requiredStocks[$item['id']] ?? 0) + $qtyNeeded;
            }
        }

        foreach ($requiredStocks as $prodId => $totalNeeded) {
            $stockMov = DB::table('stock_movements')
                ->where('product_id', $prodId)
                ->where('outlet_id', $outletId)
                ->latest('created_at')
                ->first();
                
            $available = $stockMov ? (float)$stockMov->balance_after : 0;
            
            if ($totalNeeded > $available) {
                $prodName = DB::table('products')->where('id', $prodId)->value('name');
                \Filament\Notifications\Notification::make()
                    ->title("Stok komponen kurang!")
                    ->body("Dibutuhkan {$totalNeeded} pcs untuk bahan {$prodName}. Hanya tersedia {$available} pcs.")
                    ->danger()
                    ->send();
                    
                $this->dispatch('close-payment-modal');
                return; 
            }
        }

        $transaction = DB::transaction(function () use ($outletId, $companyId) {
            
            $newTrx = Transaction::create([
                'company_id' => $companyId,
                'outlet_id' => $outletId,
                'user_id' => auth()->id(),
                'pos_session_id' => (filament()->getTenant()->hasFeature('finance.closing_shift') && $this->activeSession) ? $this->activeSession->id : null,
                'customer_id' => $this->customerId ?: null,
                'account_id' => $this->accountId, 
                'transaction_number' => 'SALE-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'type' => 'sale', 
                'in_out' => 'in', 
                'status' => 'completed',
                'payment_method' => $this->paymentMethod,
                'subtotal' => $this->getSubtotal(),
                'discount' => $this->discount ?: 0,
                'points_used' => $this->pointsToRedeem ?: 0,
                'point_discount_amount' => $this->getPointDiscountAmount(),
                'grand_total' => $this->getGrandTotal(),
                'amount_paid' => $this->amountPaid ?: $this->getGrandTotal(),
                'amount_change' => $this->getChangeAmount(),
            ]);

            \App\Models\Account::where('id', $this->accountId)->increment('balance', $this->getGrandTotal());

            if ($this->activeSession) {
                $this->activeSession->increment('total_sales', $this->getGrandTotal());
                if ($this->paymentMethod === 'cash') {
                    $this->activeSession->increment('total_cash_sales', $this->getGrandTotal());
                }
            }

            if ($this->appliedVoucher) {
                \App\Models\Voucher::where('id', $this->appliedVoucher['id'])->increment('used_count');
            }

            $company = filament()->getTenant();

            if ($this->pointsToRedeem > 0 && $this->customerId) {
                \App\Models\PointHistory::create([
                    'company_id' => $companyId,
                    'customer_id' => $this->customerId,
                    'type' => 'redeem',
                    'amount' => $this->pointsToRedeem,
                    'reference_id' => $newTrx->transaction_number,
                    'description' => 'Tukar poin di Kasir (POS)',
                ]);
            }

            if ($this->customerId && $this->customerInfo && $company->is_loyalty_enabled) {
                $spendAmount = (float) $company->loyalty_spend_amount;
                $earnedPerSpend = (int) $company->loyalty_point_earned;

                if ($spendAmount > 0) {
                    $grandTotal = $this->getGrandTotal();
                    $earnedMultiplier = floor($grandTotal / $spendAmount);
                    $earnedPoints = $earnedMultiplier * $earnedPerSpend; 
                    
                    if ($earnedPoints > 0) {
                        \App\Models\PointHistory::create([
                            'company_id' => $companyId,
                            'customer_id' => $this->customerId,
                            'type' => 'earn',
                            'amount' => $earnedPoints,
                            'reference_id' => $newTrx->transaction_number,
                            'description' => 'Earned points from POS Sale',
                        ]);
                    }
                }
            }

            foreach ($this->cart as $item) {
                $totalBaseQtyDeducted = $item['qty'] * $item['conversion_factor'];
                $isService = ($item['item_type'] ?? 'goods') === 'service';
                $isBundle = in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']);

                TransactionItem::create([
                    'company_id' => $companyId,
                    'transaction_id' => $newTrx->id,
                    'product_id' => $item['id'],
                    'uom_id' => $item['uom_id'],
                    'qty' => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'],
                    'base_qty' => $totalBaseQtyDeducted,
                    'cost_price' => ($item['cost'] ?? 0) * $item['conversion_factor'], 
                    'selling_price' => $item['price'],
                    'subtotal' => $item['price'] * $item['qty'],
                ]);

                if ($isBundle) {
                    $components = DB::table('product_components')->where('parent_product_id', $item['id'])->get();
                    
                    foreach ($components as $comp) {
                        $child = DB::table('products')->where('id', $comp->child_product_id)->first();
                        
                        if ($child && $child->item_type === 'goods') {
                            $qtyToDeduct = $totalBaseQtyDeducted * (float)$comp->quantity;
                            
                            $lastMovement = StockMovement::where('product_id', $comp->child_product_id)
                                ->where('outlet_id', $outletId)->latest()->first();
                            
                            $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0.00;
                            $balanceAfter = $balanceBefore - $qtyToDeduct;

                            StockMovement::create([
                                'company_id' => $companyId,
                                'outlet_id' => $outletId,
                                'product_id' => $comp->child_product_id,
                                'type' => 'sale', 
                                'reference_type' => Transaction::class,
                                'reference_id' => $newTrx->id,
                                'quantity' => $qtyToDeduct,
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'remarks' => "Terjual dalam Paket/Bundle. Nota: " . $newTrx->transaction_number,
                            ]);
                        }
                    }
                } elseif (!$isService) {
                    $lastMovement = StockMovement::where('product_id', $item['id'])
                        ->where('outlet_id', $outletId)
                        ->latest()
                        ->first();

                    $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0.00;
                    $balanceAfter = $balanceBefore - $totalBaseQtyDeducted;

                    StockMovement::create([
                        'company_id' => $companyId,
                        'outlet_id' => $outletId,
                        'product_id' => $item['id'],
                        'type' => 'sale', 
                        'reference_type' => Transaction::class,
                        'reference_id' => $newTrx->id,
                        'quantity' => $totalBaseQtyDeducted,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'remarks' => 'Penjualan POS Nota: ' . $newTrx->transaction_number,
                    ]);
                }
            }

            return $newTrx;
        });

        \Filament\Notifications\Notification::make()->title('Transaksi Berhasil!')->success()->send();
        
        $this->dispatch('open-receipt', url: route('pos.receipt', $transaction->id));
        $this->dispatch('close-payment-modal'); 
        
        $this->reset(['cart', 'discount', 'amountPaid', 'customerId', 'customerInfo', 'voucherCode', 'appliedVoucher', 'pointsToRedeem', 'paymentMethod', 'accountId']);
    }
}