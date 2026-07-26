<x-filament-panels::page>

    <div class="space-y-5">

        <!-- HEADER -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    Report Penjualan
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pantau performa penjualan bisnis Anda secara real-time
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    Export
                </button>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <input
                    type="date"
                    wire:model.live="startDate"
                    class="border-0 bg-transparent p-0 text-xs font-medium text-gray-700 focus:ring-0 dark:text-gray-200"
                />
                <span class="text-xs text-gray-400">—</span>
                <input
                    type="date"
                    wire:model.live="endDate"
                    class="border-0 bg-transparent p-0 text-xs font-medium text-gray-700 focus:ring-0 dark:text-gray-200"
                />
            </div>

            @if (isset($outlets) && count($outlets))
                <select wire:model.live="outletId" class="rounded-lg border-gray-200 bg-white py-2 text-xs font-medium text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    <option value="">Semua Outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <!-- KPI CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

            @php
                $kpis = [
                    [
                        'label' => 'Total Penjualan',
                        'value' => 'Rp ' . number_format($totalSales, 0, ',', '.'),
                        'change' => $totalSalesChange ?? null,
                        'color' => 'primary',
                        'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                    ],
                    [
                        'label' => 'Total Transaksi',
                        'value' => number_format($totalTrx, 0, ',', '.'),
                        'change' => $totalTrxChange ?? null,
                        'color' => 'success',
                        'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
                    ],
                    [
                        'label' => 'Rata-rata Transaksi',
                        'value' => 'Rp ' . number_format($avgTrx, 0, ',', '.'),
                        'change' => $avgTrxChange ?? null,
                        'color' => 'warning',
                        'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
                    ],
                    [
                        'label' => 'Total Item Terjual',
                        'value' => number_format($totalItems, 0, ',', '.'),
                        'change' => $totalItemsChange ?? null,
                        'color' => 'purple',
                        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                    ],
                ];
            @endphp

            @foreach ($kpis as $kpi)
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        @if ($kpi['color'] === 'purple')
                            <div class="rounded-xl bg-purple-100 p-2.5 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400">
                        @else
                            <div class="rounded-xl bg-{{ $kpi['color'] }}-100 p-2.5 text-{{ $kpi['color'] }}-600 dark:bg-{{ $kpi['color'] }}-900/40 dark:text-{{ $kpi['color'] }}-400">
                        @endif
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    </div>

                    <div class="mt-3 flex items-baseline gap-2">
                        <h3 class="text-xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                            {{ $kpi['value'] }}
                        </h3>

                        @if (!is_null($kpi['change']))
                            @php $isUp = $kpi['change'] >= 0; @endphp
                            <span @class([
                                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                                'bg-success-50 text-success-700 dark:bg-success-900/40 dark:text-success-400' => $isUp,
                                'bg-danger-50 text-danger-700 dark:bg-danger-900/40 dark:text-danger-400' => !$isUp,
                            ])>
                                <svg class="h-2.5 w-2.5 {{ $isUp ? '' : 'rotate-180' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V6.06L5.53 9.78a.75.75 0 01-1.06-1.06l5-5a.75.75 0 011.06 0l5 5a.75.75 0 11-1.06 1.06l-3.72-3.72v10.19A.75.75 0 0110 17z" clip-rule="evenodd" />
                                </svg>
                                {{ number_format(abs($kpi['change']), 1) }}%
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        vs {{ $previousPeriodLabel ?? 'periode sebelumnya' }}
                    </p>
                </div>
            @endforeach
        </div>

        <!-- CHART + DONUT -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            <!-- Grafik Penjualan -->
            <div
                wire:key="sales-chart-{{ $startDate }}-{{ $endDate }}"
                class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2"
                x-data="{
                    period: 'daily',
                    chart: null,
                    datasets: {{ Js::from($chartData ?? [
                        'daily' => ['labels' => [], 'current' => [], 'previous' => []],
                        'weekly' => ['labels' => [], 'current' => [], 'previous' => []],
                        'monthly' => ['labels' => [], 'current' => [], 'previous' => []],
                    ]) }},
                    init() {
                        this.renderChart();
                    },
                    renderChart() {
                        const ctx = this.$refs.canvas.getContext('2d');
                        const d = this.datasets[this.period];
                        if (this.chart) this.chart.destroy();
                        this.chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: d.labels,
                                datasets: [
                                    {
                                        label: 'Periode Ini',
                                        data: d.current,
                                        borderColor: '#3B82F6',
                                        backgroundColor: 'rgba(59,130,246,0.08)',
                                        borderWidth: 2,
                                        tension: 0.35,
                                        pointRadius: 0,
                                        pointHoverRadius: 5,
                                        fill: true,
                                    },
                                    {
                                        label: 'Periode Sebelumnya',
                                        data: d.previous,
                                        borderColor: '#CBD5E1',
                                        borderDash: [4, 4],
                                        borderWidth: 2,
                                        tension: 0.35,
                                        pointRadius: 0,
                                        fill: false,
                                    },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: {
                                        ticks: {
                                            callback: (v) => 'Rp ' + (v / 1000000).toFixed(0) + 'jt',
                                        },
                                        grid: { color: 'rgba(148,163,184,0.1)' },
                                    },
                                    x: { grid: { display: false } },
                                },
                            },
                        });
                    },
                }"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Grafik Penjualan</h3>

                    <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                        <template x-for="opt in [{ key: 'daily', label: 'Harian' }, { key: 'weekly', label: 'Mingguan' }, { key: 'monthly', label: 'Bulanan' }]" :key="opt.key">
                            <button
                                type="button"
                                @click="period = opt.key; renderChart()"
                                :class="period === opt.key ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                                class="rounded-md px-3 py-1.5 text-xs font-medium transition"
                                x-text="opt.label"
                            ></button>
                        </template>
                    </div>
                </div>

                <div class="mt-4 h-64" wire:ignore>
                    <canvas x-ref="canvas"></canvas>
                </div>

                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-primary-500"></span> Periode Ini</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded border-t-2 border-dashed border-gray-300"></span> Periode Sebelumnya</span>
                </div>
            </div>

            <!-- Donut: Metode Pembayaran -->
            <div
                wire:key="payment-donut-{{ $startDate }}-{{ $endDate }}"
                class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                x-data="{
                    init() {
                        const ctx = this.$refs.donut.getContext('2d');
                        new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: {{ Js::from(collect($paymentMethods)->pluck('label')) }},
                                datasets: [{
                                    data: {{ Js::from(collect($paymentMethods)->pluck('value')) }},
                                    backgroundColor: {{ Js::from(collect($paymentMethods)->pluck('color')) }},
                                    borderWidth: 0,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '72%',
                                plugins: { legend: { display: false } },
                            },
                        });
                    },
                }"
            >
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Penjualan Berdasarkan Metode Pembayaran</h3>

                @if (count($paymentMethods))
                    <div class="relative mx-auto mt-4 h-40 w-40" wire:ignore>
                        <canvas x-ref="donut"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xs text-gray-400">Total</span>
                            <span class="text-sm font-bold text-gray-950 dark:text-white">Rp {{ number_format($totalSales, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mt-5 space-y-2.5">
                        @foreach ($paymentMethods as $method)
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <span class="h-2 w-2 rounded-full" style="background-color: {{ $method['color'] }}"></span>
                                    {{ $method['label'] }}
                                </span>
                                <span class="text-right">
                                    <span class="font-medium text-gray-950 dark:text-white">Rp {{ number_format($method['value'], 0, ',', '.') }}</span>
                                    <span class="ml-1 text-xs text-gray-400">{{ number_format($method['percentage'], 1) }}%</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-8 text-center text-sm text-gray-400">Belum ada transaksi di periode ini.</p>
                @endif
            </div>
        </div>

        <!-- BOTTOM ROW: Produk Terlaris, Kategori, Ringkasan -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            <!-- Produk Terlaris -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Produk Terlaris</h3>

                @if ($topProducts->isNotEmpty())
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400">
                                <th class="pb-2 text-left font-medium">#</th>
                                <th class="pb-2 text-left font-medium">Produk</th>
                                <th class="pb-2 text-right font-medium">Terjual</th>
                                <th class="pb-2 text-right font-medium">Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topProducts as $i => $product)
                                <tr class="border-t border-gray-50 dark:border-gray-800">
                                    <td class="py-2.5 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-2">
                                            @if (!empty($product->image_url))
                                                <img src="{{ $product->image_url }}" class="h-7 w-7 rounded-lg object-cover" alt="">
                                            @else
                                                <div class="h-7 w-7 rounded-lg bg-gray-100 dark:bg-gray-800"></div>
                                            @endif
                                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $product->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 text-right text-gray-500 dark:text-gray-400">{{ number_format($product->total_qty, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right font-medium text-gray-950 dark:text-white">
                                        Rp {{ number_format($product->total_sales, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mt-8 text-center text-sm text-gray-400">Belum ada penjualan produk di periode ini.</p>
                @endif
            </div>

            <!-- Penjualan per Kategori -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Penjualan per Kategori</h3>

                @if ($categorySales->isNotEmpty())
                    <div class="mt-3 space-y-3.5">
                        @foreach ($categorySales as $i => $cat)
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-300">{{ $i + 1 }}. {{ $cat->category_name }}</span>
                                    <span class="font-medium text-gray-950 dark:text-white">Rp {{ number_format($cat->total_sales, 0, ',', '.') }}</span>
                                </div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $cat->percentage }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs text-gray-400">{{ number_format($cat->percentage, 1) }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-8 text-center text-sm text-gray-400">Belum ada penjualan per kategori di periode ini.</p>
                @endif
            </div>

            <!-- Ringkasan -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Ringkasan</h3>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total Diskon</span>
                        <span class="font-medium text-gray-950 dark:text-white">Rp {{ number_format($totalDiscount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total Pajak</span>
                        <span class="font-medium text-gray-950 dark:text-white">Rp {{ number_format($totalTax, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-success-50 px-4 py-3 dark:bg-success-900/30">
                    <span class="text-sm font-medium text-success-700 dark:text-success-400">Penjualan Bersih</span>
                    <span class="text-base font-bold text-success-700 dark:text-success-400">Rp {{ number_format($netSales, 0, ',', '.') }}</span>
                </div>

                <p class="mt-3 text-xs text-gray-400">* Semua nilai dalam IDR</p>
            </div>
        </div>

    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        @endpush
    @endonce

</x-filament-panels::page>