<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;
use Carbon\Carbon;

class FinanceReportController extends Controller
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

        $diff = $startDate->diffInSeconds($endDate);
        $prevEnd = $startDate->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($diff);

        $outlets = $isOwner ? Outlet::where('company_id', $tenantId)->get(['id', 'name']) : collect();

        // 1. BASE QUERY
        $baseQ = function ($from, $to) use ($tenantId, $isOwner, $user, $outletId) {
            return DB::table('transactions')
                ->where('company_id', $tenantId)
                ->where('status', 'completed')
                ->whereNotNull('in_out')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$from, $to])
                ->when(!$isOwner, fn($q) => $q->where('outlet_id', $user->outlet_id))
                ->when($isOwner && $outletId, fn($q) => $q->where('outlet_id', $outletId));
        };

        $currQ = $baseQ($startDate, $endDate);
        $prevQ = $baseQ($prevStart, $prevEnd);

        // 2. KALKULASI CURRENT PERIOD
        $currIn = (clone $currQ)->where('in_out', 'in')->sum('grand_total');
        $currOutTotal = (clone $currQ)->where('in_out', 'out')->sum('grand_total');
        $currOutOps = (clone $currQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])->sum('grand_total');
        $currAdminFee = (clone $currQ)->sum('admin_fee');
        $currNetCash = $currIn - $currOutTotal - $currAdminFee;
        $currPendapatan = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')->sum('grand_total');

        $currHppQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn($q) => $q->where('transactions.outlet_id', $outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $currHpp = (float) ($currHppQuery->hpp ?? 0);
        $currBebanOps = $currOutOps + $currAdminFee; 
        $currTotalBeban = $currHpp + $currBebanOps;
        $currLaba = $currPendapatan - $currTotalBeban;
        $currMargin = $currPendapatan > 0 ? ($currLaba / $currPendapatan) * 100 : 0;

        // 3. KALKULASI PREVIOUS PERIOD (Untuk % Growth)
        $prevIn = (clone $prevQ)->where('in_out', 'in')->sum('grand_total');
        $prevOutTotal = (clone $prevQ)->where('in_out', 'out')->sum('grand_total');
        $prevOutOps = (clone $prevQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])->sum('grand_total');
        $prevAdminFee = (clone $prevQ)->sum('admin_fee');
        $prevNetCash = $prevIn - $prevOutTotal - $prevAdminFee;
        $prevPendapatan = (clone $prevQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')->sum('grand_total');

        $prevHppQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$prevStart, $prevEnd])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn($q) => $q->where('transactions.outlet_id', $outletId))
            ->selectRaw('SUM(transaction_items.cost_price * transaction_items.qty) as hpp')
            ->first();

        $prevHpp = (float) ($prevHppQuery->hpp ?? 0);
        $prevBebanOps = $prevOutOps + $prevAdminFee;
        $prevTotalBeban = $prevHpp + $prevBebanOps;
        $prevLaba = $prevPendapatan - $prevTotalBeban;
        $prevMargin = $prevPendapatan > 0 ? ($prevLaba / $prevPendapatan) * 100 : 0;

        $pct = fn($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100, 1) : ($c > 0 ? 100 : 0);

        // 4. CHART DATA (GRAFIK GARIS P&L)
        $chartLabels = [];
        $chartPendapatan = [];
        $chartBeban = [];
        $chartLaba = [];

        $trendIn = (clone $currQ)->where('in_out', 'in')->where('type', '!=', 'opening_balance')
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');
        $trendOutOps = (clone $currQ)->where('in_out', 'out')->whereNotIn('type', ['purchaseorder', 'purchase'])
            ->selectRaw("DATE(created_at) as label, SUM(grand_total) as total")->groupBy('label')->pluck('total', 'label');
        $trendAdminFee = (clone $currQ)
            ->selectRaw("DATE(created_at) as label, SUM(admin_fee) as total")->groupBy('label')->pluck('total', 'label');

        $trendHpp = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.company_id', $tenantId)->where('transactions.type', 'sale')->where('transactions.status', 'completed')->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$startDate, $endDate])
            ->when(!$isOwner, fn($q) => $q->where('transactions.outlet_id', $user->outlet_id))
            ->when($isOwner && $outletId, fn($q) => $q->where('transactions.outlet_id', $outletId))
            ->selectRaw("DATE(transactions.created_at) as label, SUM(transaction_items.cost_price * transaction_items.qty) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $dateStr = $cursor->toDateString();
            $chartLabels[] = $cursor->format('d/m');
            
            $dIn = (float) ($trendIn[$dateStr] ?? 0);
            $dOutOps = (float) ($trendOutOps[$dateStr] ?? 0);
            $dHpp = (float) ($trendHpp[$dateStr] ?? 0); 
            $dAdmin = (float) ($trendAdminFee[$dateStr] ?? 0); 
            
            $dailyBeban = $dOutOps + $dHpp + $dAdmin; 
            
            $chartPendapatan[] = $dIn;
            $chartBeban[] = $dailyBeban;
            $chartLaba[] = $dIn - $dailyBeban; 
            
            $cursor->addDay();
        }

        // 5. DONUT CHART PROPORSI
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

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'kpi' => [
                'pendapatan' => ['val' => $currPendapatan, 'pct' => $pct($currPendapatan, $prevPendapatan)],
                'beban'      => ['val' => $currTotalBeban, 'pct' => $pct($currTotalBeban, $prevTotalBeban)],
                'laba'       => ['val' => $currLaba, 'pct' => $pct($currLaba, $prevLaba)],
                'cash'       => ['val' => $currNetCash, 'pct' => $pct($currNetCash, $prevNetCash)],
                'margin'     => ['val' => $currMargin, 'pct' => $pct($currMargin, $prevMargin)],
            ],
            'chart' => [
                'labels' => $chartLabels,
                'pendapatan' => $chartPendapatan,
                'beban' => $chartBeban,
                'laba' => $chartLaba,
            ],
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
                    'hpp'        => $prevHpp,
                    'bebanOps'   => $prevBebanOps,
                    'labaBersih' => $prevLaba,
                    'cashIn'     => $prevIn,
                    'cashOut'    => $prevOutTotal,
                    'netCash'    => $prevNetCash,
                ],
                'pct' => [
                    'hpp' => $pct($currHpp, $prevHpp),
                    'bebanOps' => $pct($currBebanOps, $prevBebanOps),
                    'labaBersih' => $pct($currLaba, $prevLaba),
                ]
            ]
        ]);
    }
}