<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

    <style>
        .fn-card { background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .dark .fn-card { background: #0f172a; border-color: #1e293b; }
        .kpi-icon-fn { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: white; }
        
        /* Grid CSS Murni (Anti-Purge) */
        .grid-kpi { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .grid-kpi { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1280px) { .grid-kpi { grid-template-columns: repeat(5, 1fr); } }

        .grid-charts { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-top: 1rem; }
        @media (min-width: 1024px) { 
            .grid-charts { grid-template-columns: repeat(3, 1fr); } 
            .span-2 { grid-column: span 2 / span 2; }
        }

        .grid-tables { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-top: 1rem; }
        @media (min-width: 1024px) { .grid-tables { grid-template-columns: repeat(2, 1fr); } }

        /* Table Styling */
        .fn-table { width: 100%; text-align: left; font-size: 12px; border-collapse: collapse; }
        .fn-table th { padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-weight: 600; }
        .fn-table td { padding: 10px 0; border-bottom: 1px dashed #f3f4f6; color: #374151; }
        .dark .fn-table th { border-bottom-color: #374151; color: #9ca3af; }
        .dark .fn-table td { border-bottom-color: #1f2937; color: #d1d5db; }
        .text-danger { color: #ef4444; } .dark .text-danger { color: #f87171; }
        .text-success { color: #10b981; } .dark .text-success { color: #34d399; }

        /* FILTER BAR CUSTOM UI */
        .filter-wrapper { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; background-color: #ffffff; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .dark .filter-wrapper { background-color: #0f172a; border-color: #1e293b; }
        .filter-date-group { display: flex; align-items: center; gap: 8px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 12px; }
        .dark .filter-date-group { background-color: rgba(30, 41, 59, 0.5); border-color: #334155; }
        .filter-input { background: transparent; border: none; font-size: 0.75rem; font-weight: 600; color: #475569; outline: none; padding: 0; cursor: pointer; }
        .dark .filter-input { color: #cbd5e1; color-scheme: dark; }
        .filter-select { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 32px 8px 12px; font-size: 0.75rem; font-weight: 600; color: #475569; appearance: none; outline: none; cursor: pointer; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; }
        .dark .filter-select { background-color: rgba(30, 41, 59, 0.5); border-color: #334155; color: #cbd5e1; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); }
    </style>

    <div class="space-y-4">
        
        <!-- HEADER & FILTER (SUDAH DIRAPIKAN) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan Keuangan</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pantau kondisi kesehatan finansial bisnis Anda</p>
            </div>
            
            <!-- Menggunakan custom class filter-wrapper agar rapi -->
            <div class="filter-wrapper">
                <div class="filter-date-group">
                    <svg style="width: 16px; height: 16px; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <input type="date" wire:model.live="startDate" class="filter-input">
                    <span style="font-size: 12px; color: #9ca3af; font-weight: bold;">—</span>
                    <input type="date" wire:model.live="endDate" class="filter-input">
                </div>
                
                @if(count($outlets))
                <select wire:model.live="outletId" class="filter-select">
                    <option value="">🏢 Semua Outlet</option>
                    @foreach($outlets as $o) <option value="{{$o->id}}">{{$o->name}}</option> @endforeach
                </select>
                @endif
            </div>
        </div>

        <!-- ROW 1: 5 SUPER KPI -->
        <div class="grid-kpi">
            @php
                $boxes = [
                    ['t' => 'Pendapatan', 'v' => $kpi['pendapatan']['val'], 'p' => $kpi['pendapatan']['prev'], 'pct' => $kpi['pendapatan']['pct'], 'c' => '#10b981', 'i' => 'M12 6v6m0 0v6m0-6h6m-6 0H6'],
                    ['t' => 'Total Beban', 'v' => $kpi['beban']['val'], 'p' => $kpi['beban']['prev'], 'pct' => $kpi['beban']['pct'], 'c' => '#ef4444', 'i' => 'M20 12H4'],
                    ['t' => 'Laba Bersih', 'v' => $kpi['laba']['val'], 'p' => $kpi['laba']['prev'], 'pct' => $kpi['laba']['pct'], 'c' => '#3b82f6', 'i' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['t' => 'Arus Kas Bersih', 'v' => $kpi['cash']['val'], 'p' => $kpi['cash']['prev'], 'pct' => $kpi['cash']['pct'], 'c' => '#8b5cf6', 'i' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
                    ['t' => 'Margin Laba', 'v' => $kpi['margin']['val'], 'p' => $kpi['margin']['prev'], 'pct' => $kpi['margin']['pct'], 'c' => '#f59e0b', 'i' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z', 'isPct' => true],
                ];
                $key = md5($startDate . $endDate . $outletId);
            @endphp

            @foreach($boxes as $b)
            <div class="fn-card flex flex-col justify-between p-4">
                <div class="flex items-center gap-3 mb-2">
                    <div class="kpi-icon-fn shadow-sm" style="background-color: {{ $b['c'] }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $b['i'] }}"/></svg>
                    </div>
                    <!-- UKURAN FONT DIPERKECIL DAN "k" DIHILANGKAN -->
                    <div class="overflow-hidden">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider truncate">{{ $b['t'] }}</p>
                        <h4 class="text-[14px] xl:text-[15px] font-bold text-gray-900 dark:text-white tracking-tight truncate">
                            {{ isset($b['isPct']) ? number_format($b['v'], 1) . '%' : 'Rp ' . number_format($b['v'], 0, ',', '.') }}
                        </h4>
                    </div>
                </div>
                <div class="text-[10px] flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2 mt-2">
                    @php $isUp = $b['pct'] >= 0; @endphp
                    <span class="font-bold {{ $isUp ? 'text-emerald-500' : 'text-rose-500' }}">{{ $isUp ? '▲' : '▼' }} {{ number_format(abs($b['pct']), 1) }}%</span>
                    <span class="text-gray-400">vs kmn</span>
                </div>
            </div>
            @endforeach
        </div>

        <!-- ROW 2: CHARTS -->
        <div class="grid-charts">
            <!-- Multi-line Chart -->
            <div class="fn-card span-2" wire:key="multi-chart-{{ $key }}" x-data='{
                init() {
                    new Chart(this.$refs.canvas.getContext("2d"), {
                        type: "line",
                        data: {
                            labels: @json($chartLabels),
                            datasets: [
                                { label: "Pendapatan", data: @json($chartPendapatan), borderColor: "#10b981", backgroundColor: "#10b981", tension: 0.4, borderWidth: 2, pointRadius: 2 },
                                { label: "Total Beban", data: @json($chartBeban), borderColor: "#ef4444", backgroundColor: "#ef4444", tension: 0.4, borderWidth: 2, pointRadius: 2 },
                                { label: "Laba Bersih", data: @json($chartLaba), borderColor: "#3b82f6", backgroundColor: "#3b82f6", tension: 0.4, borderWidth: 2, pointRadius: 2, borderDash: [5, 5] }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: "index", intersect: false }, plugins: { legend: { position: "top", labels: { boxWidth: 10, font: {size: 10} } } }, scales: { x: { grid: {display: false}, ticks: {font: {size: 9}} }, y: { grid: {color: "#f3f4f6"}, ticks: {font: {size: 9}, callback: v => "Rp " + (v/1000000) + "jt"} } } }
                    });
                }
            }'>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Ringkasan Keuangan</h3>
                <div class="relative h-64 w-full"><canvas x-ref="canvas"></canvas></div>
            </div>

            <!-- Donut Chart -->
            <div class="fn-card" wire:key="donut-chart-{{ $key }}" x-data='{
                init() {
                    new Chart(this.$refs.donut.getContext("2d"), {
                        type: "doughnut",
                        data: { labels: @json(array_column($proporsi, 'label')), datasets: [{ data: @json(array_column($proporsi, 'val')), backgroundColor: @json(array_column($proporsi, 'color')), borderWidth: 0 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: "75%", plugins: { legend: { display: false } } }
                    });
                }
            }'>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Proporsi Pemasukan</h3>
                <div class="relative h-40 w-full mb-6">
                    <canvas x-ref="donut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-[10px] text-gray-400">Total</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">Rp {{ number_format($totalProporsi/1000000, 1, ',', '.') }}M</span>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach($proporsi as $p)
                    <div class="flex justify-between items-center text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $p['color'] }}"></span> <span class="text-gray-600 dark:text-gray-400">{{ $p['label'] }}</span></div>
                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($p['val']/1000, 0, ',', '.') }}k</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ROW 3: LABA RUGI & ARUS KAS -->
        <div class="grid-tables">
            <!-- P&L Table -->
            <div class="fn-card">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-800 pb-2">Laporan Laba Rugi</h3>
                <table class="fn-table">
                    <thead><tr><th>Keterangan</th><th style="text-align: right;">Periode Ini</th><th style="text-align: right;">+/-</th></tr></thead>
                    <tbody>
                        <!-- Pendapatan -->
                        <tr><td class="font-bold text-gray-900 dark:text-white">Pendapatan Kotor</td><td align="right" class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($pl['curr']['pendapatan'], 0, ',', '.') }}</td><td align="right"></td></tr>
                        
                        <!-- Beban -->
                        <tr><td class="font-bold text-danger pt-4">Total Beban</td><td align="right" class="font-bold text-danger pt-4">(Rp {{ number_format($pl['curr']['totalBeban'], 0, ',', '.') }})</td><td align="right"></td></tr>
                        <tr><td class="pl-4">Harga Pokok Penjualan (HPP)</td><td align="right">(Rp {{ number_format($pl['curr']['hpp'], 0, ',', '.') }})</td><td align="right" class="text-[10px]">{{ $pl['pct']($pl['curr']['hpp'], $pl['prev']['hpp']) }}%</td></tr>
                        <tr><td class="pl-4">Beban Operasional & Lainnya</td><td align="right">(Rp {{ number_format($pl['curr']['bebanOps'], 0, ',', '.') }})</td><td align="right" class="text-[10px]">{{ $pl['pct']($pl['curr']['bebanOps'], $pl['prev']['bebanOps']) }}%</td></tr>
                        
                        <!-- Laba Bersih -->
                        <tr><td class="font-bold text-success pt-4 text-sm">LABA BERSIH</td><td align="right" class="font-bold text-success pt-4 text-sm">Rp {{ number_format($pl['curr']['labaBersih'], 0, ',', '.') }}</td><td align="right" class="text-[10px] font-bold pt-4">{{ $pl['pct']($pl['curr']['labaBersih'], $pl['prev']['labaBersih']) }}%</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Cashflow Table -->
            <div class="fn-card">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-800 pb-2">Arus Kas</h3>
                <table class="fn-table">
                    <thead><tr><th>Keterangan</th><th style="text-align: right;">Periode Ini</th><th style="text-align: right;">Periode Lalu</th></tr></thead>
                    <tbody>
                        <tr><td class="font-bold text-success">Arus Kas Masuk</td><td align="right" class="font-bold text-success">Rp {{ number_format($pl['curr']['cashIn'], 0, ',', '.') }}</td><td align="right" class="text-gray-400">Rp {{ number_format($pl['prev']['cashIn'], 0, ',', '.') }}</td></tr>
                        <tr><td class="pl-4">Penjualan & Kas Masuk</td><td align="right">Rp {{ number_format($pl['curr']['cashIn'], 0, ',', '.') }}</td><td align="right" class="text-gray-400">-</td></tr>
                        
                        <tr><td class="font-bold text-danger pt-4">Arus Kas Keluar</td><td align="right" class="font-bold text-danger pt-4">(Rp {{ number_format($pl['curr']['cashOut'], 0, ',', '.') }})</td><td align="right" class="text-gray-400">(Rp {{ number_format($pl['prev']['cashOut'], 0, ',', '.') }})</td></tr>
                        <tr><td class="pl-4">Beban & Kas Keluar</td><td align="right">(Rp {{ number_format($pl['curr']['cashOut'], 0, ',', '.') }})</td><td align="right" class="text-gray-400">-</td></tr>

                        <tr><td class="font-bold text-gray-900 dark:text-white pt-4 text-sm">ARUS KAS BERSIH</td><td align="right" class="font-bold text-gray-900 dark:text-white pt-4 text-sm">Rp {{ number_format($pl['curr']['netCash'], 0, ',', '.') }}</td><td align="right" class="text-gray-400 pt-4">Rp {{ number_format($pl['prev']['netCash'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL ASLI BAWAAN FILAMENT (Riwayat Transaksi) -->
        <div class="fn-card overflow-hidden p-0 mt-6">
            <div class="p-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Rincian Riwayat Transaksi</h3>
            </div>
            {{ $this->table }}
        </div>

    </div>
</x-filament-panels::page>