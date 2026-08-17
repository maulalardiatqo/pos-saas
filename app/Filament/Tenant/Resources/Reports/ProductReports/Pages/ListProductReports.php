<?php

namespace App\Filament\Tenant\Resources\Reports\ProductReports\Pages;

use App\Filament\Tenant\Resources\Reports\ProductReports\ProductReportResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class ListProductReports extends ListRecords
{
    protected static string $resource = ProductReportResource::class;
    protected string $view = 'filament.tenant.pages.reports.product-report';

    protected string $dateColumn = 'transactions.created_at';

    #[Url] public ?string $startDate = null;
    #[Url] public ?string $endDate = null;
    #[Url] public ?string $outletId = null;
    #[Url] public ?string $categoryId = null;
    #[Url] public ?string $brandId = null;
    #[Url] public ?string $itemType = null; 

    public function mount(): void
    {
        parent::mount();
        $this->startDate ??= now()->startOfMonth()->toDateString();
        $this->endDate ??= now()->toDateString();
    }

    protected function getHeaderActions(): array { return []; }

    protected function getViewData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();
        $tenantId = filament()->getTenant()?->id;

        // DATA MASTER
        $outlets = $isOwner ? Outlet::where('company_id', $tenantId)->orderBy('name')->get() : collect();
        $categories = Category::where('company_id', $tenantId)->orderBy('name')->get();
        $brands = Brand::where('company_id', $tenantId)->orderBy('name')->get();

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();
        $days = $start->diffInDays($end) + 1;
        
        $prevEnd = (clone $start)->subSecond();
        $prevStart = (clone $prevEnd)->subDays($days - 1)->startOfDay();

        // BASE QUERY UNTUK TRANSAKSI
        $makeBaseQuery = function (Carbon $from, Carbon $to) use ($tenantId, $isOwner, $user) {
            return DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->where('transactions.company_id', $tenantId)
                ->whereIn('transactions.type', ['sale', 'invoice'])
                ->whereIn('transactions.status', ['completed', 'pending'])
                ->whereNull('transactions.deleted_at')
                ->whereBetween($this->dateColumn, [$from, $to])
                ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
                ->when($isOwner && $this->outletId, fn ($q) => $q->where('transactions.outlet_id', $this->outletId))
                ->when($this->categoryId, fn ($q) => $q->where('products.category_id', $this->categoryId))
                ->when($this->brandId, fn ($q) => $q->where('products.brand_id', $this->brandId))
                ->when($this->itemType, fn ($q) => $q->where('products.item_type', $this->itemType));
        };

        $currentQuery = $makeBaseQuery($start, $end);
        $previousQuery = $makeBaseQuery($prevStart, $prevEnd);

        // KPI UTAMA
        $kpiCurrent = (clone $currentQuery)->select(
            DB::raw('SUM(transaction_items.base_qty) as total_qty'),
            DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
            DB::raw('SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as total_profit')
        )->first();

        $kpiPrev = (clone $previousQuery)->select(
            DB::raw('SUM(transaction_items.base_qty) as total_qty'),
            DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
            DB::raw('SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as total_profit')
        )->first();

        $qtySold = (float) $kpiCurrent->total_qty;
        $revenue = (float) $kpiCurrent->total_revenue;
        $profit = (float) $kpiCurrent->total_profit;
        $avgPrice = $qtySold > 0 ? $revenue / $qtySold : 0;
        $avgMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        $prevQty = (float) $kpiPrev->total_qty;
        $prevRev = (float) $kpiPrev->total_revenue;
        $prevAvgPrice = $prevQty > 0 ? $prevRev / $prevQty : 0;
        $prevMargin = $prevRev > 0 ? ((float) $kpiPrev->total_profit / $prevRev) * 100 : 0;

        $activeProducts = DB::table('products')->where('company_id', $tenantId)->where('is_active', 1)
            ->when($this->categoryId, fn($q) => $q->where('category_id', $this->categoryId))
            ->when($this->brandId, fn($q) => $q->where('brand_id', $this->brandId))
            ->when($this->itemType, fn($q) => $q->where('item_type', $this->itemType))
            ->count();

        $pctChange = fn (float $current, float $prev) => $prev > 0 ? round((($current - $prev) / $prev) * 100, 1) : ($current > 0 ? 100.0 : 0.0);

        // SPARKLINE
        $dailyTrend = (clone $currentQuery)
            ->select(
                DB::raw('DATE(transactions.created_at) as date'),
                DB::raw('SUM(transaction_items.base_qty) as qty'), // <--- UBAH INI
                DB::raw('SUM(transaction_items.subtotal) as rev'),
                DB::raw('SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as profit')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $sparklineLabels = [];
        $sparkData = ['qty' => [], 'rev' => [], 'price' => [], 'margin' => [], 'active' => []];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $sparklineLabels[] = $cursor->format('d M');

            $day = $dailyTrend->get($dateStr);
            $dQty = $day ? (float) $day->qty : 0;
            $dRev = $day ? (float) $day->rev : 0;
            $dProf = $day ? (float) $day->profit : 0;

            $sparkData['qty'][] = $dQty;
            $sparkData['rev'][] = $dRev;
            $sparkData['price'][] = $dQty > 0 ? $dRev / $dQty : 0;
            $sparkData['margin'][] = $dRev > 0 ? ($dProf / $dRev) * 100 : 0;
            $sparkData['active'][] = $activeProducts;

            $cursor->addDay();
        }

        $topProducts = (clone $currentQuery)
            ->select('products.name', 'products.image_url', DB::raw('SUM(transaction_items.base_qty) as total_qty'), DB::raw('SUM(transaction_items.subtotal) as total_sales')) // <--- UBAH INI
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->orderByDesc('total_sales')
            ->limit(10)->get()
            ->map(function ($item) use ($revenue) {
                $item->contribution = $revenue > 0 ? round(($item->total_sales / $revenue) * 100, 1) : 0;
                return $item;
            });
        $categorySales = (clone $currentQuery)
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(DB::raw("COALESCE(categories.name, 'Lainnya') as category_name"), DB::raw('SUM(transaction_items.subtotal) as total_sales'))
            ->groupBy('category_name')->orderByDesc('total_sales')->get()
            ->map(function ($row) use ($revenue) {
                $row->percentage = $revenue > 0 ? round(($row->total_sales / $revenue) * 100, 1) : 0;
                return $row;
            });
            
        $colors = ['#3B82F6', '#22C55E', '#F59E0B', '#A855F7', '#94A3B8', '#EF4444', '#14B8A6'];
        foreach ($categorySales as $i => $cat) { $cat->color = $colors[$i % count($colors)]; }

        // =========================================================
        // MENGHITUNG PERFORMA PRODUK (PERBAIKAN LOGIC STOK HABIS)
        // =========================================================
        $perfNew = DB::table('products')->where('company_id', $tenantId)->whereBetween('created_at', [$start, $end])->count();
        
        $targetOutletId = $this->outletId ?? ($isOwner ? null : $user->outlet_id);
        
        // PERBAIKAN: Mulai dari tabel products, lalu Left Join ke stocks
        $perfOosQuery = DB::table('products')
            ->where('products.company_id', $tenantId)
            ->where('products.item_type', '!=', 'service') // Jasa tidak dihitung sebagai stok habis
            ->when($this->categoryId, fn($q) => $q->where('products.category_id', $this->categoryId))
            ->when($this->brandId, fn($q) => $q->where('products.brand_id', $this->brandId))
            ->when($this->itemType, fn($q) => $q->where('products.item_type', $this->itemType))
            ->leftJoin('stocks', function($join) use ($targetOutletId) {
                $join->on('products.id', '=', 'stocks.product_id');
                if ($targetOutletId) {
                    $join->where('stocks.outlet_id', '=', $targetOutletId);
                }
            })
            ->select('products.id')
            ->groupBy('products.id')
            ->havingRaw('COALESCE(SUM(stocks.qty), 0) <= 0');
            
        $perfOos = $perfOosQuery->get()->count(); 

        return [
            'outlets' => $outlets, 'categories' => $categories, 'brands' => $brands,
            'qtySold' => $qtySold, 'qtyChange' => $pctChange($qtySold, $prevQty),
            'revenue' => $revenue, 'revChange' => $pctChange($revenue, $prevRev),
            'avgPrice' => $avgPrice, 'priceChange' => $pctChange($avgPrice, $prevAvgPrice),
            'avgMargin' => $avgMargin, 'marginChange' => $pctChange($avgMargin, $prevMargin),
            'activeProducts' => $activeProducts, 'activeChange' => 0,
            
            'topProducts' => $topProducts,
            'categorySales' => $categorySales,
            
            'sparklineLabels' => $sparklineLabels,
            'sparkData' => $sparkData,

            'perfNew' => $perfNew,
            'perfOos' => $perfOos, 
            'perfSlow' => 0,
        ];
    }
}