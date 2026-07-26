<?php

namespace App\Filament\Tenant\Resources\Reports\FinanceReports\Pages;

use App\Filament\Tenant\Resources\Reports\FinanceReports\FinanceReportResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ListFinanceReports extends ListRecords
{
    protected static string $resource = FinanceReportResource::class;
    
    protected string $view = 'filament.tenant.pages.reports.finance-report';

    #[Url] public $startDate;
    #[Url] public $endDate;
    #[Url] public $outletId = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    protected function getViewData(): array
    {
        $tenantId = filament()->getTenant()?->id;
        $user = auth()->user();
        $isOwner = $user->isOwner() || $user->isPlatform();

        $start = $this->startDate ? \Carbon\Carbon::parse($this->startDate)->startOfDay() : now()->startOfMonth();
        $end = $this->endDate ? \Carbon\Carbon::parse($this->endDate)->endOfDay() : now()->endOfMonth();

        $diff = $start->diffInSeconds($end);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($diff);

        $outlets = $isOwner ? \App\Models\Outlet::where('company_id', $tenantId)->get() : collect();

        // QUERY MENGGUNAKAN IN_OUT & GRAND_TOTAL
        $baseQ = function ($from, $to) use ($tenantId, $isOwner, $user) {
            return DB::table('transactions')
                ->where('company_id', $tenantId)
                ->where('status', 'completed')
                ->whereNotNull('in_out')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$from, $to])
                ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId));
        };

        $currQ = $baseQ($start, $end);
        $prevQ = $baseQ($prevStart, $prevEnd);

        // -- 1. KEUANGAN CURRENT (PERIODE INI) --
        
        // TOTAL KAS MASUK & KELUAR (Termasuk Saldo Awal) -> Untuk Cashflow
        $currIn = (clone $currQ)->where('in_out', 'in')->sum('grand_total');
        $currOut = (clone $currQ)->where('in_out', 'out')->sum('grand_total');
        $currNetCash = $currIn - $currOut;

        // PENDAPATAN MURNI (Kecualikan Saldo Awal) -> Untuk Laba Rugi
        $currPendapatan = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')->sum('grand_total');
        
        // HPP (Cost of Goods Sold) dari transaksi penjualan
        $itemCurr = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $currHpp = (float) $itemCurr->hpp;
        // Semua pengeluaran selain HPP dianggap Beban Operasional (misal bayar listrik, aset, dll)
        $currBebanOps = $currOut; 
        $currTotalBeban = $currHpp + $currBebanOps;
        
        // Laba dihitung dari Pendapatan Murni, BUKAN Total Kas Masuk
        $currLaba = $currPendapatan - $currTotalBeban;
        $currMargin = $currPendapatan > 0 ? ($currLaba / $currPendapatan) * 100 : 0;

        // -- 2. KEUANGAN PREVIOUS (PERIODE LALU UNTUK PERSENTASE) --
        
        // TOTAL KAS MASUK & KELUAR (Termasuk Saldo Awal)
        $prevIn = (clone $prevQ)->where('in_out', 'in')->sum('grand_total');
        $prevOut = (clone $prevQ)->where('in_out', 'out')->sum('grand_total');
        $prevNetCash = $prevIn - $prevOut;

        // PENDAPATAN MURNI (Kecualikan Saldo Awal)
        $prevPendapatan = (clone $prevQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')->sum('grand_total');
        
        $itemPrev = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$prevStart, $prevEnd])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $prevHpp = (float) $itemPrev->hpp;
        $prevBebanOps = $prevOut;
        $prevTotalBeban = $prevHpp + $prevBebanOps;
        $prevLaba = $prevPendapatan - $prevTotalBeban;
        $prevMargin = $prevPendapatan > 0 ? ($prevLaba / $prevPendapatan) * 100 : 0;

        $pct = fn($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100, 1) : ($c > 0 ? 100 : 0);

        // -- 3. CHART DATA (GRAFIK GARIS P&L) --
        $chartLabels = [];
        $chartPendapatan = [];
        $chartBeban = [];
        $chartLaba = [];

        // Di grafik, KITA KECUALIKAN opening_balance agar garis chart tidak rusak melonjak tajam
        $trendIn = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');
            
        $trendOut = (clone $currQ)->where('in_out', 'out')
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');

        $trendHpp = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw("DATE(transactions.created_at) as label, SUM(transaction_items.cost_price * transaction_items.qty) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $chartLabels[] = $cursor->format('d M');
            
            $dIn = (float) ($trendIn[$dateStr] ?? 0);
            $dOut = (float) ($trendOut[$dateStr] ?? 0);
            $dHpp = (float) ($trendHpp[$dateStr] ?? 0); 
            
            $dailyBeban = $dOut + $dHpp; 
            
            $chartPendapatan[] = $dIn;
            $chartBeban[] = $dailyBeban;
            $chartLaba[] = $dIn - $dailyBeban; 
            
            $cursor->addDay();
        }

        // -- 4. DONUT CHART (PROPORSI PEMASUKAN) --
        // Kita KECUALIKAN opening_balance agar proporsi murni menampilkan sumber bisnis operasional
        $proporsiData = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')
            ->selectRaw("type, SUM(grand_total) as total")
            ->groupBy('type')->orderByDesc('total')->get();

        $colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'];
        $proporsi = [];
        $totalProporsi = 0;
        
        foreach ($proporsiData as $i => $row) {
            $totalProporsi += $row->total;
            $label = match($row->type) { 
                'sale' => 'Penjualan POS', 
                'revenue' => 'Pemasukan Ekstra', 
                'invoice' => 'Pembayaran Tagihan',
                default => strtoupper($row->type) 
            };
            $proporsi[] = [
                'label' => $label,
                'val' => (float)$row->total,
                'color' => $colors[$i % count($colors)]
            ];
        }

        return [
            'outlets' => $outlets,
            'kpi' => [
                'pendapatan' => ['val' => $currPendapatan, 'prev' => $prevPendapatan, 'pct' => $pct($currPendapatan, $prevPendapatan)],
                'beban'      => ['val' => $currTotalBeban, 'prev' => $prevTotalBeban, 'pct' => $pct($currTotalBeban, $prevTotalBeban)],
                'laba'       => ['val' => $currLaba, 'prev' => $prevLaba, 'pct' => $pct($currLaba, $prevLaba)],
                'cash'       => ['val' => $currNetCash, 'prev' => $prevNetCash, 'pct' => $pct($currNetCash, $prevNetCash)],
                'margin'     => ['val' => $currMargin, 'prev' => $prevMargin, 'pct' => $pct($currMargin, $prevMargin)],
            ],
            'chartLabels' => $chartLabels,
            'chartPendapatan' => $chartPendapatan,
            'chartBeban' => $chartBeban,
            'chartLaba' => $chartLaba,
            'proporsi' => $proporsi,
            'totalProporsi' => $totalProporsi,
            'pl' => [
                'curr' => [
                    'pendapatan' => $currPendapatan, 
                    'totalBeban' => $currTotalBeban,
                    'hpp'        => $currHpp,
                    'bebanOps'   => $currBebanOps,
                    'labaBersih' => $currLaba,
                    'cashIn'     => $currIn,
                    'cashOut'    => $currOut,
                    'netCash'    => $currNetCash,
                ],
                'prev' => [
                    'pendapatan' => $prevPendapatan,
                    'totalBeban' => $prevTotalBeban,
                    'hpp'        => $prevHpp,
                    'bebanOps'   => $prevBebanOps,
                    'labaBersih' => $prevLaba,
                    'cashIn'     => $prevIn,
                    'cashOut'    => $prevOut,
                    'netCash'    => $prevNetCash,
                ],
                'pct' => $pct
            ]
        ];
    }
}