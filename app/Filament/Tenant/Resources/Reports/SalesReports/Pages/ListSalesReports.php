<?php

namespace App\Filament\Tenant\Resources\Reports\SalesReports\Pages;

use App\Filament\Tenant\Resources\Reports\SalesReports\SalesReportResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\Transaction;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class ListSalesReports extends ListRecords
{
    protected static string $resource = SalesReportResource::class;

    protected string $view = 'filament.tenant.pages.reports.sales-report';

    // Ganti ini kalau kolom tanggal transaksimu bukan created_at
    protected string $dateColumn = 'created_at';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?string $outletId = null;

    public function mount(): void
    {
        parent::mount();

        $this->startDate ??= now()->startOfMonth()->toDateString();
        $this->endDate ??= now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();
        $tenantId = filament()->getTenant()?->id;

        // Daftar outlet untuk dropdown filter — hanya owner/platform yang boleh lihat & pilih semua outlet
        $outlets = $isOwner
            ? Outlet::query()->where('company_id', $tenantId)->orderBy('name')->get()
            : collect();

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        // Periode sebelumnya: durasi yang sama, persis sebelum $start
        $days = $start->diffInDays($end) + 1;
        $prevEnd = (clone $start)->subSecond();
        $prevStart = (clone $prevEnd)->subDays($days - 1)->startOfDay();

        $makeBaseQuery = function (Carbon $from, Carbon $to) use ($tenantId, $isOwner, $user) {
            return Transaction::query()
                ->where('company_id', $tenantId)
                ->where('type', 'sale')
                ->where('status', 'completed')
                ->whereBetween($this->dateColumn, [$from, $to])
                ->when(!$isOwner, fn ($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId));
        };

        $currentQuery = $makeBaseQuery($start, $end);
        $previousQuery = $makeBaseQuery($prevStart, $prevEnd);

        // 1. KPI CARDS (periode ini)
        $totalSales = (float) (clone $currentQuery)->sum('grand_total');
        $totalTrx = (clone $currentQuery)->count();
        $avgTrx = $totalTrx > 0 ? $totalSales / $totalTrx : 0;

        $totalItems = (float) DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween("transactions.{$this->dateColumn}", [$start, $end])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn ($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->sum('transaction_items.qty');

        // 1b. KPI CARDS (periode sebelumnya, untuk hitung %)
        $prevTotalSales = (float) (clone $previousQuery)->sum('grand_total');
        $prevTotalTrx = (clone $previousQuery)->count();
        $prevAvgTrx = $prevTotalTrx > 0 ? $prevTotalSales / $prevTotalTrx : 0;

        $prevTotalItems = (float) DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween("transactions.{$this->dateColumn}", [$prevStart, $prevEnd])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn ($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->sum('transaction_items.qty');

        $pctChange = fn (float $current, float $previous) => $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : ($current > 0 ? 100.0 : 0.0);

        // 2. METODE PEMBAYARAN (DONUT CHART)
        $paymentMethodMeta = [
            'cash' => ['label' => 'Tunai', 'color' => '#3B82F6'],
            'qris' => ['label' => 'QRIS', 'color' => '#22C55E'],
            'debit_card' => ['label' => 'Kartu Debit', 'color' => '#F59E0B'],
            'credit_card' => ['label' => 'Kartu Kredit', 'color' => '#A855F7'],
        ];

        $rawPaymentMethods = (clone $currentQuery)
            ->select('payment_method', DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $paymentTotal = (float) $rawPaymentMethods->sum();

        $paymentMethods = $rawPaymentMethods->map(function ($total, $key) use ($paymentMethodMeta, $paymentTotal) {
            $meta = $paymentMethodMeta[$key] ?? ['label' => 'Lainnya', 'color' => '#94A3B8'];

            return [
                'label' => $meta['label'],
                'color' => $meta['color'],
                'value' => (float) $total,
                'percentage' => $paymentTotal > 0 ? round(((float) $total / $paymentTotal) * 100, 1) : 0,
            ];
        })->sortByDesc('value')->values()->all();

        // 3. PRODUK TERLARIS (TOP 5)
        $topProducts = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.company_id', $tenantId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween("transactions.{$this->dateColumn}", [$start, $end])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn ($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->select(
                'products.name',
                'products.image_url',
                DB::raw('SUM(transaction_items.qty) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_sales')
            )
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        // 4. PENJUALAN PER KATEGORI (TOP 5)
        $categorySales = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.company_id', $tenantId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween("transactions.{$this->dateColumn}", [$start, $end])
            ->when(!$isOwner, fn ($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn ($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->select(
                DB::raw("COALESCE(categories.name, 'Lainnya') as category_name"),
                DB::raw('SUM(transaction_items.subtotal) as total_sales')
            )
            ->groupBy('category_name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($totalSales) {
                $row->percentage = $totalSales > 0 ? round(((float) $row->total_sales / $totalSales) * 100, 1) : 0;

                return $row;
            });

        // 5. RINGKASAN KEUANGAN
        $totalDiscount = (float) (clone $currentQuery)->sum('discount');
        $totalTax = (float) (clone $currentQuery)->sum('tax');
        $netSales = $totalSales - $totalDiscount;

        // 6. TREN GRAFIK (harian, lalu diagregasi ke mingguan & bulanan)
        $dailyCurrent = (clone $currentQuery)
            ->select(DB::raw("DATE({$this->dateColumn}) as d"), DB::raw('SUM(grand_total) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('total', 'd');

        $dailyPrevious = (clone $previousQuery)
            ->select(DB::raw("DATE({$this->dateColumn}) as d"), DB::raw('SUM(grand_total) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('total', 'd');

        $chartData = $this->buildChartData($start, $end, $dailyCurrent, $prevStart, $prevEnd, $dailyPrevious);

        return [
            'totalSales' => $totalSales,
            'totalSalesChange' => $pctChange($totalSales, $prevTotalSales),
            'totalTrx' => $totalTrx,
            'totalTrxChange' => $pctChange($totalTrx, $prevTotalTrx),
            'avgTrx' => $avgTrx,
            'avgTrxChange' => $pctChange($avgTrx, $prevAvgTrx),
            'totalItems' => $totalItems,
            'totalItemsChange' => $pctChange($totalItems, $prevTotalItems),

            'periodLabel' => $start->translatedFormat('d M Y') . ' - ' . $end->translatedFormat('d M Y'),
            'previousPeriodLabel' => $prevStart->translatedFormat('d M Y') . ' - ' . $prevEnd->translatedFormat('d M Y'),

            'outlets' => $outlets,
            'paymentMethods' => $paymentMethods,
            'topProducts' => $topProducts,
            'categorySales' => $categorySales,

            'totalDiscount' => $totalDiscount,
            'totalTax' => $totalTax,
            'netSales' => $netSales,

            'chartData' => $chartData,
        ];
    }

    /**
     * Susun label harian di rentang tanggal, lalu agregasikan ke mingguan & bulanan.
     * "previous" dijejerkan berdasarkan urutan index (bukan tanggal asli) supaya
     * bisa dioverlay sebagai garis pembanding di chart.
     */
    protected function buildChartData(
        Carbon $start,
        Carbon $end,
        $dailyCurrent,
        Carbon $prevStart,
        Carbon $prevEnd,
        $dailyPrevious
    ): array {
        $daily = ['labels' => [], 'current' => [], 'previous' => []];

        $cursor = $start->copy();
        $prevCursor = $prevStart->copy();

        while ($cursor->lte($end)) {
            $daily['labels'][] = $cursor->translatedFormat('d M');
            $daily['current'][] = (float) ($dailyCurrent[$cursor->toDateString()] ?? 0);
            $daily['previous'][] = (float) ($dailyPrevious[$prevCursor->toDateString()] ?? 0);
            $cursor->addDay();
            $prevCursor->addDay();
        }

        $weekly = $this->bucketize($start, $daily['labels'], $daily['current'], $daily['previous'], 7, 'W%s');
        $monthly = $this->bucketize($start, $daily['labels'], $daily['current'], $daily['previous'], 30, 'Bulan %s');

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    protected function bucketize(Carbon $start, array $labels, array $current, array $previous, int $size, string $labelFormat): array
    {
        $out = ['labels' => [], 'current' => [], 'previous' => []];
        $chunksCurrent = array_chunk($current, $size);
        $chunksPrevious = array_chunk($previous, $size);
        $chunksLabels = array_chunk($labels, $size);

        foreach ($chunksCurrent as $i => $chunk) {
            $out['labels'][] = ($chunksLabels[$i][0] ?? '') . ($size > 1 ? ' - ' . end($chunksLabels[$i]) : '');
            $out['current'][] = array_sum($chunk);
            $out['previous'][] = array_sum($chunksPrevious[$i] ?? []);
        }

        return $out;
    }
}