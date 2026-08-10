<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Outlet;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = ($user->company ?? $user->tenant)->id;
        $isOwner = $user->isOwner() || $user->isPlatform();

        // Parameter Filter
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfMonth();
        $outletId = $request->query('outlet_id');

        $days = $startDate->diffInDays($endDate) + 1;
        $prevEnd = (clone $startDate)->subSecond();
        $prevStart = (clone $prevEnd)->subDays($days - 1)->startOfDay();

        $outlets = $isOwner ? Outlet::where('company_id', $tenantId)->get(['id', 'name']) : collect();

        // BASE QUERY FUNCTION
        $makeBaseQuery = function ($from, $to) use ($tenantId, $isOwner, $user, $outletId) {
            return Transaction::query()
                ->where('company_id', $tenantId)
                ->where('type', 'sale')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->when(!$isOwner, fn ($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $outletId, fn ($q) => $q->where('outlet_id', $outletId));
        };

        $currentQuery = $makeBaseQuery($startDate, $endDate);
        $previousQuery = $makeBaseQuery($prevStart, $prevEnd);

        // 1. KPI CARDS
        $totalSales = (float) (clone $currentQuery)->sum('grand_total');
        $totalTrx = (clone $currentQuery)->count();
        $avgTrx = $totalTrx > 0 ? $totalSales / $totalTrx : 0;

        $totalItemsQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween("transactions.created_at", [$startDate, $endDate])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->sum('transaction_items.qty');
        $totalItems = (float) $totalItemsQuery;

        // KPI PREVIOUS
        $prevTotalSales = (float) (clone $previousQuery)->sum('grand_total');
        $prevTotalTrx = (clone $previousQuery)->count();
        $prevAvgTrx = $prevTotalTrx > 0 ? $prevTotalSales / $prevTotalTrx : 0;

        $prevTotalItemsQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween("transactions.created_at", [$prevStart, $prevEnd])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->sum('transaction_items.qty');
        $prevTotalItems = (float) $prevTotalItemsQuery;

        $pctChange = fn ($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100.0 : 0.0);

        // 2. METODE PEMBAYARAN
        $paymentMethodMeta = [
            'cash' => ['label' => 'Tunai', 'color' => '#3B82F6'],
            'qris' => ['label' => 'QRIS', 'color' => '#22C55E'],
            'debit_card' => ['label' => 'Kartu Debit', 'color' => '#F59E0B'],
            'credit_card' => ['label' => 'Kartu Kredit', 'color' => '#A855F7'],
        ];

        $rawPaymentMethods = (clone $currentQuery)->select('payment_method', DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')->pluck('total', 'payment_method');
        $paymentTotal = (float) $rawPaymentMethods->sum();

        $paymentMethods = $rawPaymentMethods->map(function ($total, $key) use ($paymentMethodMeta, $paymentTotal) {
            $meta = $paymentMethodMeta[$key] ?? ['label' => 'Lainnya', 'color' => '#94A3B8'];
            return [
                'label' => $meta['label'],
                'color' => $meta['color'],
                'value' => (float) $total,
                'pct' => $paymentTotal > 0 ? round(((float) $total / $paymentTotal) * 100, 1) : 0,
            ];
        })->sortByDesc('value')->values()->all();

        // 3. TOP 5 PRODUCTS
        $topProducts = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween("transactions.created_at", [$startDate, $endDate])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select('products.name', 'products.image_url', DB::raw('SUM(transaction_items.qty) as total_qty'), DB::raw('SUM(transaction_items.subtotal) as total_sales'))
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->orderByDesc('total_sales')->limit(5)->get();

        // 4. CATEGORY SALES
        $categorySales = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween("transactions.created_at", [$startDate, $endDate])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(DB::raw("COALESCE(categories.name, 'Lainnya') as category_name"), DB::raw('SUM(transaction_items.subtotal) as total_sales'))
            ->groupBy('category_name')->orderByDesc('total_sales')->limit(5)->get()
            ->map(function ($row) use ($totalSales) {
                $row->percentage = $totalSales > 0 ? round(((float) $row->total_sales / $totalSales) * 100, 1) : 0;
                return $row;
            });

        // 5. SUMMARY
        $totalDiscount = (float) (clone $currentQuery)->sum('discount');
        $totalTax = (float) (clone $currentQuery)->sum('tax');
        $netSales = $totalSales - $totalDiscount;

        // 6. CHART TREN PENJUALAN
        $dailyCurrent = (clone $currentQuery)->select(DB::raw("DATE(created_at) as d"), DB::raw('SUM(grand_total) as total'))
            ->groupBy('d')->orderBy('d')->pluck('total', 'd');
        $dailyPrevious = (clone $previousQuery)->select(DB::raw("DATE(created_at) as d"), DB::raw('SUM(grand_total) as total'))
            ->groupBy('d')->orderBy('d')->pluck('total', 'd');

        $chartLabels = [];
        $chartCurr = [];
        $chartPrev = [];
        $cursor = $startDate->copy();
        $prevCursor = $prevStart->copy();

        while ($cursor->lte($endDate)) {
            $chartLabels[] = $cursor->format('d/m');
            $chartCurr[] = (float) ($dailyCurrent[$cursor->toDateString()] ?? 0);
            $chartPrev[] = (float) ($dailyPrevious[$prevCursor->toDateString()] ?? 0);
            $cursor->addDay();
            $prevCursor->addDay();
        }

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'kpi' => [
                'sales' => ['val' => $totalSales, 'pct' => $pctChange($totalSales, $prevTotalSales)],
                'trx'   => ['val' => $totalTrx, 'pct' => $pctChange($totalTrx, $prevTotalTrx)],
                'avg'   => ['val' => $avgTrx, 'pct' => $pctChange($avgTrx, $prevAvgTrx)],
                'items' => ['val' => $totalItems, 'pct' => $pctChange($totalItems, $prevTotalItems)],
            ],
            'chart' => [
                'labels' => $chartLabels,
                'current' => $chartCurr,
                'previous' => $chartPrev,
            ],
            'payments' => $paymentMethods,
            'top_products' => $topProducts,
            'top_categories' => $categorySales,
            'summary' => [
                'discount' => $totalDiscount,
                'tax' => $totalTax,
                'net' => $netSales
            ]
        ]);
    }
}