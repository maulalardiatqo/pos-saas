<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

    <style>
        /* Card Dasar */
        .report-card { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05); }
        .dark .report-card { background-color: #0f172a; border-color: #1e293b; }
        
        /* KPI Atas */
        .icon-box-sm { width: 40px !important; height: 40px !important; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .svg-icon { width: 20px !important; height: 20px !important; display: block; }
        .sparkline-container { height: 40px; width: 100%; margin-top: 10px; }

        /* TABEL TOP 10 (Custom CSS agar tidak hilang di-purge Tailwind) */
        .pr-table { width: 100%; text-align: left; border-collapse: collapse; font-size: 0.75rem; }
        .pr-table th { padding-bottom: 8px; font-weight: 600; border-bottom: 1px solid #e5e7eb; color: #9ca3af; }
        .pr-table td { padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .dark .pr-table th { border-bottom-color: #374151; }
        .dark .pr-table td { border-bottom-color: #1f2937; }
        
        /* Progress Bar Top 10 */
        .progress-track { flex: 1; height: 6px; background-color: #f3f4f6; border-radius: 999px; overflow: hidden; display: flex; }
        .dark .progress-track { background-color: #374151; }
        .progress-fill { height: 100%; background-color: #3b82f6; border-radius: 999px; }

        /* Tabel Legenda Donut Chart */
        .legend-table { width: 100%; font-size: 0.75rem; border-collapse: collapse; margin-top: 10px; }
        .legend-table td { padding: 6px 0; }
        
        /* Kotak Performa Mini (CSS Grid Murni) */
        .perf-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .perf-box { padding: 12px; border: 1px solid #e5e7eb; border-radius: 12px; background-color: #f9fafb; }
        .dark .perf-box { border-color: #374151; background-color: rgba(31, 41, 55, 0.5); }
        .perf-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* FILTER BAR CUSTOM UI */
        .filter-wrapper { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; background-color: #ffffff; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .dark .filter-wrapper { background-color: #0f172a; border-color: #1e293b; }

        .filter-date-group { display: flex; align-items: center; gap: 8px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 12px; transition: all 0.2s; }
        .dark .filter-date-group { background-color: rgba(30, 41, 59, 0.5); border-color: #334155; }
        .filter-date-group:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }

        .filter-input { background: transparent; border: none; font-size: 0.75rem; font-weight: 600; color: #475569; outline: none; padding: 0; cursor: pointer; }
        .dark .filter-input { color: #cbd5e1; color-scheme: dark; }
        .filter-input:focus { outline: none; box-shadow: none; }

        .filter-select { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 32px 8px 12px; font-size: 0.75rem; font-weight: 600; color: #475569; appearance: none; outline: none; cursor: pointer; transition: all 0.2s; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; }
        .dark .filter-select { background-color: rgba(30, 41, 59, 0.5); border-color: #334155; color: #cbd5e1; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); }
        .filter-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    </style>

    <div class="space-y-5">
        <!-- HEADER & FILTER BAR -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan Produk</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pantau performa produk dan kontribusinya terhadap penjualan</p>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Export
                </button>
                <button class="px-4 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Cetak
                </button>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-wrapper">
            
            <!-- Date Range Group -->
            <div class="filter-date-group">
                <svg style="width: 16px; height: 16px; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <input type="date" wire:model.live="startDate" class="filter-input">
                <span style="font-size: 12px; color: #9ca3af; font-weight: bold;">—</span>
                <input type="date" wire:model.live="endDate" class="filter-input">
            </div>

            <!-- Select Filters Group -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                
                <!-- FILTER TIPE ITEM -->
                <select wire:model.live="itemType" class="filter-select">
                    <option value="">📦 Semua Tipe</option>
                    <option value="goods">🛒 Barang Saja</option>
                    <option value="service">🛠️ Jasa Saja</option>
                </select>

                @if(count($outlets))
                <select wire:model.live="outletId" class="filter-select">
                    <option value="">🏢 Semua Outlet</option>
                    @foreach($outlets as $o) <option value="{{$o->id}}">{{$o->name}}</option> @endforeach
                </select>
                @endif

                <select wire:model.live="categoryId" class="filter-select">
                    <option value="">📂 Semua Kategori</option>
                    @foreach($categories as $c) <option value="{{$c->id}}">{{$c->name}}</option> @endforeach
                </select>

                <select wire:model.live="brandId" class="filter-select">
                    <option value="">🏷️ Semua Merek</option>
                    @foreach($brands as $b) <option value="{{$b->id}}">{{$b->name}}</option> @endforeach
                </select>
            </div>
            
        </div>

       <!-- KPI 5 KOLOM (DENGAN SPARKLINE) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @php
                $kpis = [
                    ['title' => 'Total Produk Terjual', 'val' => number_format($qtySold, 0, ',', '.'), 'chg' => $qtyChange, 'color' => '#3B82F6', 'bg' => 'bg-blue-500', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'chart' => $sparkData['qty']],
                    ['title' => 'Total Penjualan', 'val' => 'Rp ' . number_format($revenue, 0, ',', '.'), 'chg' => $revChange, 'color' => '#22C55E', 'bg' => 'bg-emerald-500', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'chart' => $sparkData['rev']],
                    ['title' => 'Rata-rata Harga Jual', 'val' => 'Rp ' . number_format($avgPrice, 0, ',', '.'), 'chg' => $priceChange, 'color' => '#A855F7', 'bg' => 'bg-purple-500', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'chart' => $sparkData['price']],
                    ['title' => 'Margin Rata-rata', 'val' => number_format($avgMargin, 1) . '%', 'chg' => $marginChange, 'color' => '#F59E0B', 'bg' => 'bg-amber-500', 'icon' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z', 'chart' => $sparkData['margin']],
                    ['title' => 'Total Produk Aktif', 'val' => number_format($activeProducts, 0, ',', '.'), 'chg' => $activeChange, 'color' => '#EC4899', 'bg' => 'bg-pink-500', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'chart' => $sparkData['active']],
                ];
                
                // BIKIN KUNCI UNIK DARI GABUNGAN FILTER
                $filterKey = md5($startDate . $endDate . $outletId . $categoryId . $brandId . $itemType);
            @endphp

            @foreach($kpis as $index => $kpi)
            <!-- MENGGUNAKAN SINGLE QUOTES (') PADA X-DATA AGAR JSON BISA DIBACA -->
            <div class="report-card relative overflow-hidden flex flex-col justify-between" 
                 wire:key="kpi-sparkline-{{ $index }}-{{ $filterKey }}" 
                 x-data='{
                init() {
                    const ctx = this.$refs.canvas.getContext("2d");
                    const bgColor = "{{ $kpi['color'] }}33"; 
                    
                    new Chart(ctx, {
                        type: "line",
                        data: { 
                            labels: @json($sparklineLabels), 
                            datasets: [{ 
                                data: @json($kpi["chart"]), 
                                borderColor: "{{ $kpi['color'] }}", 
                                backgroundColor: bgColor,
                                borderWidth: 2, 
                                tension: 0.4, 
                                pointRadius: 0,
                                pointHoverRadius: 4, 
                                fill: true 
                            }] 
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            interaction: {
                                mode: "index",
                                intersect: false,
                            },
                            plugins: { 
                                legend: { display: false }, 
                                tooltip: { 
                                    enabled: true,
                                    displayColors: false,
                                    backgroundColor: "rgba(15, 23, 42, 0.9)",
                                    titleFont: { size: 10 },
                                    bodyFont: { size: 12, weight: "bold" },
                                    callbacks: {
                                        label: function(context) {
                                            let val = context.raw;
                                            return val.toLocaleString("id-ID"); 
                                        }
                                    }
                                } 
                            }, 
                            scales: { 
                                x: { display: false }, 
                                y: { display: false, min: 0 } 
                            }, 
                            layout: { padding: { top: 5, bottom: 0, left: 0, right: 0 } } 
                        }
                    });
                }
            }'>
                <div class="flex items-start gap-3">
                    <div class="icon-box-sm text-white {{ $kpi['bg'] }}" style="background-color: {{ $kpi['color'] }}">
                        <svg class="svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $kpi['title'] }}</p>
                        <div class="flex items-baseline gap-1.5 mt-0.5">
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $kpi['val'] }}</h4>
                            @php $isUp = $kpi['chg'] >= 0; @endphp
                            <span class="text-[9px] font-semibold px-1 rounded {{ $isUp ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50' }}">
                                {{ $isUp ? '▲' : '▼' }} {{ number_format(abs($kpi['chg']), 1) }}%
                            </span>
                        </div>
                    </div>
                </div>
                <div class="sparkline-container"><canvas x-ref="canvas"></canvas></div>
            </div>
            @endforeach
        </div>

        <!-- MAIN CONTENT GRID (2 Kiri : 1 Kanan) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            
            <!-- ===================== KIRI: Top 10 Produk ===================== -->
            <div class="report-card lg:col-span-2 overflow-x-auto">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Top 10 Produk Terlaris</h3>
                    <select class="text-xs border-gray-200 rounded-lg py-1 dark:bg-gray-800 dark:border-gray-700 text-gray-600"><option>Berdasarkan Kuantitas</option></select>
                </div>

                <table class="pr-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th>Produk</th>
                            <th style="text-align: center; width: 140px;">Terjual</th>
                            <th style="text-align: right; width: 120px;">Penjualan</th>
                            <th style="text-align: right; width: 80px;">Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProducts as $i => $p)
                        <tr>
                            <td class="text-gray-400">{{ $i + 1 }}</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="{{ $p->image_url ?? 'https://placehold.co/100' }}" style="width: 32px; height: 32px; border-radius: 6px; object-fit: cover; flex-shrink: 0;">
                                    <span style="font-weight: 600;" class="text-gray-900 dark:text-white">{{ $p->name }}</span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="progress-track">
                                        <!-- Logika Panjang Bar (Maksimal width 100%) -->
                                        <div class="progress-fill" style="width: {{ $i == 0 ? 100 : round(($p->total_qty / $topProducts[0]->total_qty)*100) }}%;"></div>
                                    </div>
                                    <span style="font-weight: 600; width: 40px; text-align: right;" class="text-gray-600 dark:text-gray-300">{{ number_format($p->total_qty, 0, ',', '.') }}</span>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 600;" class="text-gray-900 dark:text-white">Rp {{ number_format($p->total_sales, 0, ',', '.') }}</td>
                            <td style="text-align: right; color: #6b7280;">{{ $p->contribution }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align: center; padding: 20px 0; color: #9ca3af;">Belum ada data penjualan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- ===================== KANAN: Donut Chart & Performa ===================== -->
            <div class="space-y-5">
                
                <!-- Kategori Donut Chart -->
                <div class="report-card" wire:key="donut-chart-{{ $filterKey }}" x-data='{
                    init() {
                        new Chart(this.$refs.donut, {
                            type: "doughnut",
                            data: { 
                                labels: @json(collect($categorySales)->pluck("category_name")), 
                                datasets: [{ 
                                    data: @json(collect($categorySales)->pluck("total_sales")), 
                                    backgroundColor: @json(collect($categorySales)->pluck("color")), 
                                    borderWidth: 0 
                                }] 
                            },
                            options: { 
                                responsive: true, 
                                maintainAspectRatio: false, 
                                cutout: "75%", 
                                plugins: { 
                                    legend: { display: false }, 
                                    tooltip: { callbacks: { label: function(c) { return " Rp " + (c.raw/1000).toLocaleString("id-ID") + "k"; } } } 
                                } 
                            }
                        });
                    }
                }'>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Penjualan per Kategori</h3>
                    
                    <div style="position: relative; width: 140px; height: 140px; margin: 0 auto;">
                        <canvas x-ref="donut"></canvas>
                        <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                            <span style="font-size: 9px; color: #9ca3af;">Total Penjualan</span>
                            <span style="font-size: 12px; font-weight: 700;" class="text-gray-900 dark:text-white">Rp {{ number_format($revenue/1000000, 1, ',', '.') }}M</span>
                        </div>
                    </div>

                    <!-- Legenda Menggunakan HTML Table Murni (Anti-Purge & Anti-Tabrakan) -->
                    <table class="legend-table">
                        @foreach($categorySales as $cat)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="width: 10px; height: 10px; border-radius: 50%; background-color: {{ $cat->color }}; display: inline-block; flex-shrink: 0;"></span>
                                    <span class="text-gray-600 dark:text-gray-300">{{ $cat->category_name }}</span>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 600;" class="text-gray-900 dark:text-white">Rp {{ number_format($cat->total_sales/1000, 0, ',', '.') }}k</td>
                            <td style="text-align: right; color: #9ca3af; width: 45px;">{{ $cat->percentage }}%</td>
                        </tr>
                        @endforeach
                    </table>
                </div>

                <!-- Performa Mini Cards (Menggunakan Custom Grid Class CSS) -->
                <div class="report-card">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Performa Produk</h3>
                    
                    <div class="perf-grid">
                        <!-- Box 1 -->
                        <div class="perf-box">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div class="perf-icon" style="background-color: #dbeafe; color: #2563eb;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <span style="font-size: 10px; font-weight: 500; color: #6b7280;">Produk Baru</span>
                            </div>
                            <span style="font-size: 1.125rem; font-weight: 700;" class="text-gray-900 dark:text-white">{{ $perfNew }}</span>
                        </div>

                        <!-- Box 2 -->
                        <div class="perf-box">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div class="perf-icon" style="background-color: #d1fae5; color: #059669;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                                <span style="font-size: 10px; font-weight: 500; color: #6b7280;">Stok Habis</span>
                            </div>
                            <span style="font-size: 1.125rem; font-weight: 700;" class="text-gray-900 dark:text-white">{{ $perfOos }}</span>
                        </div>

                        <!-- Box 3 -->
                        <div class="perf-box">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div class="perf-icon" style="background-color: #fef3c7; color: #d97706;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span style="font-size: 10px; font-weight: 500; color: #6b7280;">Slow Moving</span>
                            </div>
                            <span style="font-size: 1.125rem; font-weight: 700;" class="text-gray-900 dark:text-white">{{ $perfSlow }}</span>
                        </div>

                        <!-- Box 4 -->
                        <div class="perf-box">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <div class="perf-icon" style="background-color: #f3e8ff; color: #9333ea;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <span style="font-size: 10px; font-weight: 500; color: #6b7280;">Repeat Cust.</span>
                            </div>
                            <span style="font-size: 1.125rem; font-weight: 700;" class="text-gray-900 dark:text-white">0%</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- TABEL ANALISIS PRODUK (FILAMENT NATIVE TABLE) -->
        <div class="report-card overflow-hidden p-0">
            <div class="p-5 border-b border-gray-100 dark:border-gray-800 pb-4">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Analisis Produk Lengkap</h3>
            </div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>