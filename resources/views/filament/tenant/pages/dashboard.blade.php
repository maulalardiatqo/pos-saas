<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

    <style>
        /* Desain Card & Badge */
        .db-card { background: #ffffff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; }
        .dark .db-card { background: #0f172a; border-color: #1e293b; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2); }
        .badge-up { background: #ecfdf5; color: #059669; }
        .badge-down { background: #fef2f2; color: #e11d48; }
        .dark .badge-up { background: rgba(5, 150, 105, 0.2); color: #34d399; }
        .dark .badge-down { background: rgba(225, 29, 72, 0.2); color: #fb7185; }
        .kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: white; }
        
        /* GRID CUSTOM (ANTI-PURGE TAILWIND) */
        .grid-kpi { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .grid-kpi { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1280px) { .grid-kpi { grid-template-columns: repeat(5, 1fr); } }

        .grid-charts { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { 
            .grid-charts { grid-template-columns: repeat(3, 1fr); } 
            .span-2 { grid-column: span 2 / span 2; }
        }

        .grid-bottom { display: grid; gap: 1.25rem; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { 
            .grid-bottom { grid-template-columns: repeat(12, 1fr); }
            .span-3 { grid-column: span 3 / span 3; }
            .span-4 { grid-column: span 4 / span 4; }
            .span-5 { grid-column: span 5 / span 5; }
        }

        .grid-cat { display: grid; gap: 0.75rem; grid-template-columns: repeat(2, 1fr); }
    </style>

    <div class="space-y-6 w-full">
        
        <!-- HEADER & FILTERS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selamat datang kembali, {{ explode(' ', $user->name)[0] }}! 👋</p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                @if(count($outlets))
                <select wire:model.live="outletId" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold rounded-xl px-4 py-2 outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer text-gray-700 dark:text-gray-300 shadow-sm">
                    <option value="">🏢 Semua Outlet</option>
                    @foreach($outlets as $o) <option value="{{$o->id}}">{{$o->name}}</option> @endforeach
                </select>
                @endif
                
                <select wire:model.live="dateFilter" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold rounded-xl px-4 py-2 outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer text-gray-700 dark:text-gray-300 shadow-sm">
                    <option value="today">📅 Hari Ini</option>
                    <option value="yesterday">📅 Kemarin</option>
                    <option value="this_week">📅 Minggu Ini</option>
                    <option value="this_month">📅 Bulan Ini</option>
                </select>
            </div>
        </div>

        <!-- ROW 1: 5 SUPER KPI -->
        <div class="grid-kpi">
            @php
                $boxes = [
                    ['title' => 'Total Penjualan', 'val' => 'Rp ' . number_format($kpis['sales']['val'], 0, ',', '.'), 'prev' => 'Rp ' . number_format($kpis['sales']['prev']/1000000, 1, ',', '.') . 'jt', 'pct' => $kpis['sales']['pct'], 'color' => '#3b82f6', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                    ['title' => 'Total Transaksi', 'val' => number_format($kpis['trx']['val'], 0, ',', '.'), 'prev' => $kpis['trx']['prev'], 'pct' => $kpis['trx']['pct'], 'color' => '#10b981', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    ['title' => 'Rata-rata Transaksi', 'val' => 'Rp ' . number_format($kpis['avg']['val'], 0, ',', '.'), 'prev' => 'Rp ' . number_format($kpis['avg']['prev']/1000, 0, ',', '.') . 'k', 'pct' => $kpis['avg']['pct'], 'color' => '#f59e0b', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['title' => 'Laba Kotor', 'val' => 'Rp ' . number_format($kpis['profit']['val'], 0, ',', '.'), 'prev' => 'Rp ' . number_format($kpis['profit']['prev']/1000000, 1, ',', '.') . 'jt', 'pct' => $kpis['profit']['pct'], 'color' => '#8b5cf6', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                    ['title' => 'Produk Terjual', 'val' => number_format($kpis['items']['val'], 0, ',', '.'), 'prev' => $kpis['items']['prev'], 'pct' => $kpis['items']['pct'], 'color' => '#2563eb', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ];
                $key = md5($dateFilter . $outletId);
            @endphp

            @foreach($boxes as $box)
            <div class="db-card flex flex-col justify-between">
                <div class="flex gap-4">
                    <div class="kpi-icon shadow-sm" style="background-color: {{ $box['color'] }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $box['icon'] }}"/></svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ $box['title'] }}</p>
                        <h4 class="text-xl font-bold text-gray-900 dark:text-white leading-none">{{ $box['val'] }}</h4>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    @php $isUp = $box['pct'] >= 0; @endphp
                    <span class="font-bold px-1.5 py-0.5 rounded flex items-center gap-0.5 {{ $isUp ? 'badge-up' : 'badge-down' }}">
                        {{ $isUp ? '▲' : '▼' }} {{ number_format(abs($box['pct']), 1) }}%
                    </span>
                    <span class="text-gray-400">vs kmn ({{ $box['prev'] }})</span>
                </div>
            </div>
            @endforeach
        </div>

        <!-- ROW 2: CHART & DONUT -->
        <div class="grid-charts">
            <!-- AREA CHART -->
            <div class="db-card span-2 flex flex-col" wire:key="main-chart-{{ $key }}" x-data='{
                init() {
                    const ctx = this.$refs.canvas.getContext("2d");
                    const grad = ctx.createLinearGradient(0, 0, 0, 300);
                    grad.addColorStop(0, "rgba(59, 130, 246, 0.4)");
                    grad.addColorStop(1, "rgba(59, 130, 246, 0.0)");
                    
                    new Chart(ctx, {
                        type: "line",
                        data: { 
                            labels: @json($chartLabels), 
                            datasets: [{ 
                                data: @json($chartData), 
                                borderColor: "#3b82f6", 
                                backgroundColor: grad,
                                borderWidth: 3, tension: 0.4, pointRadius: 3, pointBackgroundColor: "#fff", fill: true 
                            }] 
                        },
                        options: { 
                            responsive: true, maintainAspectRatio: false, 
                            interaction: { mode: "index", intersect: false },
                            plugins: { legend: { display: false }, tooltip: { backgroundColor: "#0f172a", bodyFont: {size: 14, weight: "bold"}, callbacks: { label: c => "Rp " + c.raw.toLocaleString("id-ID") } } }, 
                            scales: { 
                                x: { grid: { display: false }, ticks: { color: "#94a3b8", font: {size: 10} } }, 
                                y: { border: { display: false }, grid: { color: "#f1f5f9" }, ticks: { color: "#94a3b8", font: {size: 10}, callback: v => "Rp " + (v/1000000) + "jt" } } 
                            } 
                        }
                    });
                }
            }'>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-6">Grafik Penjualan</h3>
                <div class="flex-1 relative min-h-[250px]"><canvas x-ref="canvas"></canvas></div>
            </div>

            <!-- DONUT CHART (Metode Pembayaran) -->
            <div class="db-card" wire:key="donut-chart-{{ $key }}" x-data='{
                init() {
                    new Chart(this.$refs.donut, {
                        type: "doughnut",
                        data: { 
                            labels: @json($paymentMethods->pluck("label")), 
                            datasets: [{ data: @json($paymentMethods->pluck("value")), backgroundColor: @json($paymentMethods->pluck("color")), borderWidth: 0 }] 
                        },
                        options: { responsive: true, maintainAspectRatio: false, cutout: "70%", plugins: { legend: { display: false } } }
                    });
                }
            }'>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-6">Metode Pembayaran</h3>
                
                @if(count($paymentMethods) > 0)
                <div class="relative w-40 h-40 mx-auto mb-6">
                    <canvas x-ref="donut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-[10px] text-gray-400">Total</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">Rp {{ number_format($kpis['sales']['val']/1000000, 1, ',', '.') }}M</span>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($paymentMethods as $pm)
                    <div class="flex justify-between items-center text-xs">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $pm['color'] }}"></span> <span class="text-gray-600 dark:text-gray-300 font-medium">{{ $pm['label'] }}</span></div>
                        <span class="font-bold text-gray-900 dark:text-white w-20 text-right">Rp {{ number_format($pm['value']/1000, 0, ',', '.') }}k</span>
                        <span class="text-gray-400 w-10 text-right">{{ $pm['pct'] }}%</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="flex items-center justify-center h-full text-sm text-gray-400">Tidak ada transaksi</div>
                @endif
            </div>
        </div>

       <!-- ROW 3: KATEGORI, PRODUK TERLARIS, KAS & NOTIF -->
        <div class="grid-bottom">
            
           <!-- Kategori Cards (Kiri) -->
            <div class="db-card span-3 flex flex-col">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-6">Penjualan Kategori</h3>
                
                @if(count($topCategories) > 0)
                    <div class="space-y-6 flex-1">
                        @php 
                            // Warna modern & solid untuk aksen
                            $catColors = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899']; 
                        @endphp
                        
                        @foreach($topCategories as $i => $cat)
                        @php $color = $catColors[$i % 5]; @endphp
                        
                        <div class="group">
                            <div class="flex justify-between items-end mb-2">
                                <!-- Nama Kategori -->
                                <div class="flex items-center gap-2">
                                    <span style="background-color: {{ $color }}; box-shadow: 0 0 8px {{ $color }}60;" class="w-2.5 h-2.5 rounded-full block"></span>
                                    <span class="text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider" style="font-size: 0.75rem;">
                                        {{ $cat->name }}
                                    </span>
                                </div>
                                <!-- Nominal & Persentase -->
                                <div class="text-right flex items-baseline gap-1.5">
                                    <span class="text-sm font-black text-gray-900 dark:text-white leading-none">
                                        Rp {{ number_format($cat->total/1000, 0, ',', '.') }}k
                                    </span>
                                    <span class="text-[10px] font-bold text-gray-400">
                                        ({{ $cat->pct }}%)
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Progress Bar Super Tipis & Modern -->
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                <div style="background-color: {{ $color }}; width: {{ $cat->pct }}%;" 
                                     class="h-full rounded-full transition-all duration-1000 ease-out group-hover:brightness-110">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-1 items-center justify-center text-sm font-medium text-gray-400">
                        Belum ada data kategori
                    </div>
                @endif
            </div>

            <!-- Produk Terlaris (Tengah) -->
            <div class="db-card span-5 overflow-hidden p-0 flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Produk Terlaris</h3>
                </div>
                <div class="flex-1 overflow-x-auto p-4 pt-0">
                    <table style="width: 100%; font-size: 12px; text-align: left; border-collapse: collapse; margin-top: 10px;">
                        <thead>
                            <tr style="color: #9ca3af; border-bottom: 1px solid #f3f4f6;">
                                <th style="padding-bottom: 8px; font-weight: 500; width: 30px;">#</th>
                                <th style="padding-bottom: 8px; font-weight: 500;">Produk</th>
                                <th style="padding-bottom: 8px; font-weight: 500; text-align: center;">Terjual</th>
                                <th style="padding-bottom: 8px; font-weight: 500; text-align: right;">Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProducts as $i => $p)
                            <tr style="border-bottom: 1px solid #f9fafb;">
                                <td style="padding: 10px 0; color: #9ca3af;">{{ $i+1 }}</td>
                                <td style="padding: 10px 0; font-weight: 600; color: #111827;" class="dark:text-white">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="{{ $p->image_url ?? 'https://placehold.co/100' }}" style="width: 28px; height: 28px; border-radius: 6px; object-fit: cover;"> 
                                        <span>{{ $p->name }}</span>
                                    </div>
                                </td>
                                <td style="padding: 10px 0; text-align: center; color: #4b5563; font-weight: 500;" class="dark:text-gray-300">{{ $p->qty }}</td>
                                <td style="padding: 10px 0; text-align: right; font-weight: 700; color: #111827;" class="dark:text-white">Rp {{ number_format($p->total, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ringkasan Kas & Notifikasi (Kanan) -->
            <div class="span-4 flex flex-col gap-5">
                <!-- Kas -->
                <div class="db-card">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Ringkasan Kas (Tunai)</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center"><span class="flex items-center gap-2 text-gray-600 dark:text-gray-300"><div class="w-2 h-2 rounded-full bg-emerald-500"></div> Kas Masuk</span> <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($cash['in'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between items-center"><span class="flex items-center gap-2 text-gray-600 dark:text-gray-300"><div class="w-2 h-2 rounded-full bg-rose-500"></div> Kas Keluar</span> <span class="font-bold text-rose-600">Rp {{ number_format($cash['out'], 0, ',', '.') }}</span></div>
                        <div class="pt-3 mt-1 border-t border-dashed border-gray-200 dark:border-gray-700 flex justify-between items-center">
                            <span class="font-bold text-gray-900 dark:text-white">Saldo Kasir</span> 
                            <span class="text-lg font-black text-emerald-600">Rp {{ number_format($cash['balance'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notifikasi -->
                <div class="db-card flex-1">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Notifikasi</h3>
                    <div class="space-y-4">
                        @if($lowStockCount > 0)
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
                            <div><p class="text-xs font-bold text-gray-900 dark:text-white">Stok hampir habis!</p><p class="text-[11px] text-gray-500">{{ $lowStockCount }} produk perlu restock segera.</p></div>
                        </div>
                        @endif
                        
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
                            <div><p class="text-xs font-bold text-gray-900 dark:text-white">Sistem Aktif</p><p class="text-[11px] text-gray-500">POS berfungsi normal dan terekam.</p></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>