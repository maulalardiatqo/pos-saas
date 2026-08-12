<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Outlet;
use Carbon\Carbon;

class ProductReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = ($user->company ?? $user->tenant)->id;
        $isOwner = $user->isOwner() || $user->isPlatform();

        // Ambil Parameter Filter
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfDay();
        
        $outletId = $request->query('outlet_id');
        $categoryId = $request->query('category_id');
        $brandId = $request->query('brand_id');
        $itemType = $request->query('item_type');

        // Setup Periode Sebelumnya (Untuk perbandingan)
        $days = $startDate->diffInDays($endDate) + 1;
        $prevEnd = (clone $startDate)->subSecond();
        $prevStart = (clone $prevEnd)->subDays($days - 1)->startOfDay();

        // Ambil Data Master untuk Dropdown Filter
        $outlets = $isOwner ? Outlet::where('company_id', $tenantId)->orderBy('name')->get(['id', 'name']) : collect();
        $categories = Category::where('company_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $brands = Brand::where('company_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        // Fungsi Base Query Builder
        $makeBaseQuery = function (Carbon $from, Carbon $to) use ($tenantId, $isOwner, $user, $outletId, $categoryId, $brandId, $itemType) {
            return DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->where('transactions.company_id', $tenantId)
                ->where('transactions.type', 'sale')
                ->where('transactions.status', 'completed')
                ->whereBetween('transactions.created_at', [$from, $to])
                ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
                ->when($isOwner && $outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
                ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
                ->when($brandId, fn ($q) => $q->where('products.brand_id', $brandId))
                ->when($itemType, fn ($q) => $q->where('products.item_type', $itemType));
        };

        $currentQuery = $makeBaseQuery($startDate, $endDate);
        $previousQuery = $makeBaseQuery($prevStart, $prevEnd);

        // 1. HITUNG KPI UTAMA
        $kpiCurrent = (clone $currentQuery)->select(
            DB::raw('COALESCE(SUM(transaction_items.qty), 0) as total_qty'),
            DB::raw('COALESCE(SUM(transaction_items.subtotal), 0) as total_revenue'),
            DB::raw('COALESCE(SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)), 0) as total_profit')
        )->first();

        $kpiPrev = (clone $previousQuery)->select(
            DB::raw('COALESCE(SUM(transaction_items.qty), 0) as total_qty'),
            DB::raw('COALESCE(SUM(transaction_items.subtotal), 0) as total_revenue'),
            DB::raw('COALESCE(SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)), 0) as total_profit')
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
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
            ->when($itemType, fn($q) => $q->where('item_type', $itemType))
            ->count();

        $pctChange = fn (float $current, float $prev) => $prev > 0 ? round((($current - $prev) / $prev) * 100, 1) : ($current > 0 ? 100.0 : 0.0);

        // 2. DATA TREN HARIAN (UNTUK SPARKLINE)
        $dailyTrend = (clone $currentQuery)
            ->select(
                DB::raw('DATE(transactions.created_at) as date'),
                DB::raw('SUM(transaction_items.qty) as qty'),
                DB::raw('SUM(transaction_items.subtotal) as rev'),
                DB::raw('SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as profit')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $sparklineLabels = [];
        $sparkData = ['qty' => [], 'rev' => [], 'price' => [], 'margin' => [], 'active' => []];

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
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
            $sparkData['active'][] = $activeProducts; // Konstan

            $cursor->addDay();
        }

        // 3. TOP 10 PRODUK
        $topProducts = (clone $currentQuery)
            ->select('products.name', 'products.image_url', DB::raw('SUM(transaction_items.qty) as total_qty'), DB::raw('SUM(transaction_items.subtotal) as total_sales'))
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->orderByDesc('total_sales')
            ->limit(10)->get()
            ->map(function ($item) use ($revenue) {
                $item->contribution = $revenue > 0 ? round(($item->total_sales / $revenue) * 100, 1) : 0;
                return $item;
            });

        // 4. KATEGORI PENJUALAN (DONUT CHART)
        $categorySales = (clone $currentQuery)
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(DB::raw("COALESCE(categories.name, 'Lainnya') as category_name"), DB::raw('SUM(transaction_items.subtotal) as total_sales'))
            ->groupBy('category_name')->orderByDesc('total_sales')->get()
            ->map(function ($row) use ($revenue) {
                $row->percentage = $revenue > 0 ? round(($row->total_sales / $revenue) * 100, 1) : 0;
                return $row;
            });
            
        $colors = ['#3B82F6', '#22C55E', '#F59E0B', '#A855F7', '#94A3B8', '#EF4444', '#14B8A6'];
        foreach ($categorySales as $i => $cat) { 
            $cat->color = $colors[$i % count($colors)]; 
        }

        // =========================================================
        // 5. PERFORMA PRODUK (Stok Habis Membaca Langsung dari tabel `stocks`)
        // =========================================================
        $perfNew = DB::table('products')->where('company_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate])->count();
        
        $targetOutletId = $outletId ?? ($isOwner ? null : $user->outlet_id);
        
        $perfOosQuery = DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.company_id', $tenantId)
            ->where('products.item_type', 'goods')
            ->select('stocks.product_id', DB::raw('SUM(stocks.qty) as total_qty'))
            ->groupBy('stocks.product_id')
            ->having('total_qty', '<=', 0);
            
        if ($targetOutletId) {
            $perfOosQuery->where('stocks.outlet_id', $targetOutletId);
        }
        
        $perfOos = $perfOosQuery->get()->count(); 

        // 6. ANALISIS PRODUK LENGKAP (TABEL BAWAH)
        // Ambil 50 produk teratas berdasarkan omset untuk ditampilkan di tabel analisis
        $productAnalysis = (clone $currentQuery)
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.name',
                'products.sku',
                'products.image_url',
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.qty) as qty_terjual'),
                DB::raw('SUM(transaction_items.subtotal) as omset'),
                DB::raw('SUM(transaction_items.qty * transaction_items.cost_price) as hpp'),
                DB::raw('SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as laba')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.image_url', 'categories.name')
            ->orderByDesc('omset')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'filters' => [
                'outlets' => $outlets,
                'categories' => $categories,
                'brands' => $brands,
            ],
            'kpi' => [
                'qty' => ['val' => $qtySold, 'pct' => $pctChange($qtySold, $prevQty)],
                'revenue' => ['val' => $revenue, 'pct' => $pctChange($revenue, $prevRev)],
                'price' => ['val' => $avgPrice, 'pct' => $pctChange($avgPrice, $prevAvgPrice)],
                'margin' => ['val' => $avgMargin, 'pct' => $pctChange($avgMargin, $prevMargin)],
                'active' => ['val' => $activeProducts, 'pct' => 0],
            ],
            'sparkline' => [
                'labels' => $sparklineLabels,
                'data' => $sparkData
            ],
            'top_products' => $topProducts,
            'category_sales' => $categorySales,
            'performance' => [
                'new' => $perfNew,
                'oos' => $perfOos, // <-- Sudah dinamis & ringan dari tabel stocks!
                'slow' => 0, // Placeholder
                'repeat' => '0%' // Placeholder
            ],
            'analysis' => $productAnalysis
        ]);
    }
}