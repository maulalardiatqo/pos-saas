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

        // QUERY TRANSAKSI UTAMA (Mengecualikan 'invoice' dari query status completed biasa agar tidak dobel)
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

        // QUERY KHUSUS PEMBAYARAN CICILAN / INVOICE (Membaca transaction_payments)
        $basePaymentQ = function ($from, $to) use ($tenantId, $isOwner, $user) {
            return DB::table('transaction_payments')
                ->where('company_id', $tenantId)
                ->where('payment_status', 'success')
                ->whereBetween('payment_date', [$from, $to])
                ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId));
        };

        $currQ = $baseQ($start, $end);
        $prevQ = $baseQ($prevStart, $prevEnd);

        $currPayQ = $basePaymentQ($start, $end);
        $prevPayQ = $basePaymentQ($prevStart, $prevEnd);

        // =======================================================
        // -- 1. KEUANGAN CURRENT (PERIODE INI) --
        // =======================================================
        
        // TOTAL UANG MASUK REAL (Arus Kas IN) = Penjualan Tunai POS/Revenue + Seluruh Cicilan Invoice yang masuk di periode ini
        $currInNonInvoice = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'invoice')->sum('grand_total');
        $currInInvoicePayments = (clone $currPayQ)->sum('amount');
        $currIn = $currInNonInvoice + $currInInvoicePayments;
        
        // TOTAL UANG KELUAR REAL (Untuk Arus Kas)
        $currOutTotal = (clone $currQ)->where('in_out', 'out')->sum('grand_total');
        
        // BEBAN OPERASIONAL MURNI (Untuk Laba Rugi)
        $currOutOps = (clone $currQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])->sum('grand_total');
        
        $currAdminFee = (clone $currQ)->sum('admin_fee'); // TOTAL POTONGAN MIDTRANS

        // Arus Kas Bersih (Net Cash)
        $currNetCash = $currIn - $currOutTotal - $currAdminFee;

        // PENDAPATAN MURNI (Accrual Basis: Menghitung POS + Nilai Kontrak Invoice Penjualan tanpa peduli sudah lunas/belum)
        $currPendapatanPOS = (clone $currQ)->where('in_out', 'in')->whereNotIn('type', ['opening_balance', 'invoice'])->sum('grand_total');
        $currPendapatanInvoice = DB::table('transactions')
            ->where('company_id', $tenantId)->where('type', 'invoice')->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId))
            ->sum('grand_total');

        $currPendapatan = $currPendapatanPOS + $currPendapatanInvoice;
        
        // HPP (Cost of Goods Sold) dari transaksi penjualan POS & Invoice
        $itemCurr = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)
            ->whereIn('transactions.type', ['sale', 'invoice'])
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $currHpp = (float) $itemCurr->hpp;
        
        $currBebanOps = $currOutOps + $currAdminFee; 
        $currTotalBeban = $currHpp + $currBebanOps;
        
        // Laba Bersih
        $currLaba = $currPendapatan - $currTotalBeban;
        $currMargin = $currPendapatan > 0 ? ($currLaba / $currPendapatan) * 100 : 0;

        // =======================================================
        // -- 2. KEUANGAN PREVIOUS (PERIODE LALU) --
        // =======================================================
        $prevInNonInvoice = (clone $prevQ)->where('in_out', 'in')->where('type', '!=', 'invoice')->sum('grand_total');
        $prevInInvoicePayments = (clone $prevPayQ)->sum('amount');
        $prevIn = $prevInNonInvoice + $prevInInvoicePayments;

        $prevOutTotal = (clone $prevQ)->where('in_out', 'out')->sum('grand_total');
        $prevOutOps = (clone $prevQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])->sum('grand_total');
        $prevAdminFee = (clone $prevQ)->sum('admin_fee');

        $prevNetCash = $prevIn - $prevOutTotal - $prevAdminFee;

        $prevPendapatanPOS = (clone $prevQ)->where('in_out', 'in')->whereNotIn('type', ['opening_balance', 'invoice'])->sum('grand_total');
        $prevPendapatanInvoice = DB::table('transactions')
            ->where('company_id', $tenantId)->where('type', 'invoice')->whereNull('deleted_at')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId))
            ->sum('grand_total');

        $prevPendapatan = $prevPendapatanPOS + $prevPendapatanInvoice;
        
        $itemPrev = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)
            ->whereIn('transactions.type', ['sale', 'invoice'])
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$prevStart, $prevEnd])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('transactions.outlet_id', $this->outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $prevHpp = (float) $itemPrev->hpp;
        $prevBebanOps = $prevOutOps + $prevAdminFee;
        $prevTotalBeban = $prevHpp + $prevBebanOps;
        $prevLaba = $prevPendapatan - $prevTotalBeban;
        $prevMargin = $prevPendapatan > 0 ? ($prevLaba / $prevPendapatan) * 100 : 0;

        $pct = fn($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100, 1) : ($c > 0 ? 100 : 0);

        // =======================================================
        // -- 3. CHART DATA (GRAFIK GARIS P&L HARIAN) --
        // =======================================================
        $chartLabels = [];
        $chartPendapatan = [];
        $chartBeban = [];
        $chartLaba = [];

        $trendInPOS = (clone $currQ)->where('in_out', 'in')->whereNotIn('type', ['opening_balance', 'invoice'])
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');

        $trendInInvoice = DB::table('transactions')
            ->where('company_id', $tenantId)->where('type', 'invoice')->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
            ->when($isOwner && $this->outletId, fn($q) => $q->where('outlet_id', $this->outletId))
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');

        $trendOutOps = (clone $currQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');

        $trendAdminFee = (clone $currQ)
            ->selectRaw("DATE(created_at) as label, SUM(admin_fee) as total")->groupBy('label')->pluck('total', 'label');

        $trendHpp = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->whereIn('transactions.type', ['sale', 'invoice'])->whereNull('transactions.deleted_at')
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
            
            $dInPOS = (float) ($trendInPOS[$dateStr] ?? 0);
            $dInInv = (float) ($trendInInvoice[$dateStr] ?? 0);
            $dIn = $dInPOS + $dInInv;

            $dOutOps = (float) ($trendOutOps[$dateStr] ?? 0);
            $dHpp = (float) ($trendHpp[$dateStr] ?? 0); 
            $dAdmin = (float) ($trendAdminFee[$dateStr] ?? 0); 
            
            $dailyBeban = $dOutOps + $dHpp + $dAdmin; 
            
            $chartPendapatan[] = $dIn;
            $chartBeban[] = $dailyBeban;
            $chartLaba[] = $dIn - $dailyBeban; 
            
            $cursor->addDay();
        }

        // =======================================================
        // -- 4. DONUT CHART (PROPORSI PEMASUKAN) --
        // =======================================================
        $proporsiData = (clone $currQ)->where('in_out', 'in')->whereNotIn('type', ['opening_balance', 'invoice'])
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
                default => strtoupper($row->type) 
            };
            $proporsi[] = [
                'label' => $label,
                'val' => (float)$row->total,
                'color' => $colors[$i % count($colors)]
            ];
        }

        // Masukkan Penjualan Invoice ke Donut Chart jika ada
        if ($currPendapatanInvoice > 0) {
            $totalProporsi += $currPendapatanInvoice;
            $proporsi[] = [
                'label' => 'Invoice Penjualan (Tempo)',
                'val' => (float)$currPendapatanInvoice,
                'color' => '#8b5cf6'
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
                    'adminFee'   => $currAdminFee, 
                    'labaBersih' => $currLaba,
                    'cashIn'     => $currIn,
                    'cashOut'    => $currOutTotal,
                    'netCash'    => $currNetCash,
                ],
                'prev' => [
                    'pendapatan' => $prevPendapatan,
                    'totalBeban' => $prevTotalBeban,
                    'hpp'        => $prevHpp,
                    'bebanOps'   => $prevBebanOps,
                    'adminFee'   => $prevAdminFee, 
                    'labaBersih' => $prevLaba,
                    'cashIn'     => $prevIn,
                    'cashOut'    => $prevOutTotal,
                    'netCash'    => $prevNetCash,
                ],
                'pct' => $pct
            ]
        ];
    }
}