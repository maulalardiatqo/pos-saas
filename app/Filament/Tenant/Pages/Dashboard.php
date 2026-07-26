<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use App\Models\Outlet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use BackedEnum; 

class Dashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';
    
    protected ?string $heading = ''; 
    
    protected static ?string $title = 'Dashboard';
    protected static ?string $slug = ''; 
    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.tenant.pages.dashboard';

    // State Filter
    #[Url] public string $dateFilter = 'today';
    #[Url] public ?string $outletId = null;

    public function getViewData(): array
    {
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();
        $tenantId = filament()->getTenant()?->id;

        $outlets = $isOwner ? Outlet::where('company_id', $tenantId)->orderBy('name')->get() : collect();

        // 1. Tentukan Rentang Waktu
        $now = now();
        $start = match ($this->dateFilter) {
            'yesterday' => $now->copy()->subDay()->startOfDay(),
            'this_week' => $now->copy()->startOfWeek(),
            'this_month' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(), // today
        };
        $end = match ($this->dateFilter) {
            'yesterday' => $now->copy()->subDay()->endOfDay(),
            'this_week' => $now->copy()->endOfWeek(),
            'this_month' => $now->copy()->endOfMonth(),
            default => $now->copy()->endOfDay(),
        };

        // Rentang waktu periode sebelumnya (untuk perbandingan)
        $diffInSeconds = $start->diffInSeconds($end);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($diffInSeconds);

        // 2. Base Query
        $baseQ = function ($from, $to) use ($tenantId, $isOwner, $user) {
            return DB::table('transactions')
                ->where('company_id', $tenantId)
                ->whereBetween('created_at', [$from, $to])
                ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId));
        };

        $currQ = $baseQ($start, $end);
        $prevQ = $baseQ($prevStart, $prevEnd);

        // 3. KALKULASI KPI SUPER (Hanya Penjualan)
        $salesData = (clone $currQ)->where('type', 'sale')->where('status', 'completed')
            ->selectRaw('SUM(grand_total) as total_rev, COUNT(id) as total_trx')->first();
        
        $totalSales = (float) $salesData->total_rev;
        $totalTrx = (int) $salesData->total_trx;
        $avgTrx = $totalTrx > 0 ? $totalSales / $totalTrx : 0;

        // Ambil Laba Kotor & Qty Terjual dari transaction_items
        $itemCurrent = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw('SUM(transaction_items.qty) as total_qty, SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as profit')
            ->first();

        $totalProfit = (float) $itemCurrent->profit;
        $totalItems = (int) $itemCurrent->total_qty;

        // Data Previous (Untuk %)
        $prevSalesData = (clone $prevQ)->where('type', 'sale')->where('status', 'completed')
            ->selectRaw('SUM(grand_total) as total_rev, COUNT(id) as total_trx')->first();
        
        $prevSales = (float) $prevSalesData->total_rev;
        $prevTrx = (int) $prevSalesData->total_trx;
        $prevAvg = $prevTrx > 0 ? $prevSales / $prevTrx : 0;

        $itemPrev = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$prevStart, $prevEnd])
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->selectRaw('SUM(transaction_items.qty) as total_qty, SUM(transaction_items.subtotal - (transaction_items.cost_price * transaction_items.qty)) as profit')
            ->first();

        $prevProfit = (float) $itemPrev->profit;
        $prevItems = (int) $itemPrev->total_qty;

        $pct = fn($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100, 1) : ($c > 0 ? 100 : 0);

        // 4. GRAFIK TREN PENJUALAN
        $isHourly = in_array($this->dateFilter, ['today', 'yesterday']);
        $trendFormat = $isHourly ? 'HOUR(created_at)' : 'DATE(created_at)';
        
        $trendQuery = (clone $currQ)->where('type', 'sale')->where('status', 'completed')
            ->selectRaw("$trendFormat as label, SUM(grand_total) as total")
            ->groupBy('label')->pluck('total', 'label');

        $chartLabels = [];
        $chartData = [];

        if ($isHourly) {
            for ($i = 0; $i < 24; $i++) {
                $chartLabels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $chartData[] = (float) ($trendQuery[$i] ?? 0);
            }
        } else {
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $dateStr = $cursor->toDateString();
                $chartLabels[] = $cursor->format('d M');
                $chartData[] = (float) ($trendQuery[$dateStr] ?? 0);
                $cursor->addDay();
            }
        }

        // 5. METODE PEMBAYARAN & KATEGORI & TOP PRODUK
        $paymentMethods = (clone $currQ)->where('type', 'sale')->where('status', 'completed')
            ->selectRaw("payment_method, SUM(grand_total) as total")
            ->groupBy('payment_method')->orderByDesc('total')->get()
            ->map(function ($row) use ($totalSales) {
                $colors = ['cash' => '#3B82F6', 'qris' => '#22C55E', 'debit_card' => '#F59E0B', 'credit_card' => '#A855F7'];
                return [
                    'label' => ucwords(str_replace('_', ' ', $row->payment_method)),
                    'value' => (float) $row->total,
                    'pct' => $totalSales > 0 ? round(($row->total / $totalSales) * 100, 1) : 0,
                    'color' => $colors[$row->payment_method] ?? '#94A3B8'
                ];
            });

        $topCategories = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')
            ->selectRaw("COALESCE(categories.name, 'Lainnya') as name, SUM(transaction_items.subtotal) as total")
            ->groupBy('name')->orderByDesc('total')->limit(4)->get()
            ->map(function($row) use ($totalSales) {
                $row->pct = $totalSales > 0 ? round(($row->total / $totalSales) * 100, 1) : 0;
                return $row;
            });

        $topProducts = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')
            ->selectRaw("products.name, products.image_url, SUM(transaction_items.qty) as qty, SUM(transaction_items.subtotal) as total")
            ->groupBy('products.id', 'products.name', 'products.image_url')->orderByDesc('total')->limit(5)->get();

        // =========================================================
        // 6. PERBAIKAN: RINGKASAN KAS MENGGUNAKAN in_out & amount_paid
        // =========================================================
        $cashIn = (clone $currQ)
            ->where('in_out', 'in')
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->sum('grand_total'); // Gunakan uang riil

        $cashOut = (clone $currQ)
            ->where('in_out', 'out')
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        $cashBalance = $cashIn - $cashOut;

        $lowStockProducts = DB::table('products')->where('company_id', $tenantId)->where('is_active', 1)->limit(3)->get(); // Ganti dengan logic stok asli Anda

        return [
            'outlets' => $outlets,
            'user' => $user,
            'kpis' => [
                'sales' => ['val' => $totalSales, 'prev' => $prevSales, 'pct' => $pct($totalSales, $prevSales)],
                'trx' => ['val' => $totalTrx, 'prev' => $prevTrx, 'pct' => $pct($totalTrx, $prevTrx)],
                'avg' => ['val' => $avgTrx, 'prev' => $prevAvg, 'pct' => $pct($avgTrx, $prevAvg)],
                'profit' => ['val' => $totalProfit, 'prev' => $prevProfit, 'pct' => $pct($totalProfit, $prevProfit)],
                'items' => ['val' => $totalItems, 'prev' => $prevItems, 'pct' => $pct($totalItems, $prevItems)],
            ],
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'paymentMethods' => $paymentMethods,
            'topCategories' => $topCategories,
            'topProducts' => $topProducts,
            'cash' => ['in' => $cashIn, 'out' => $cashOut, 'balance' => $cashBalance],
            'lowStockCount' => count($lowStockProducts)
        ];
    }
}