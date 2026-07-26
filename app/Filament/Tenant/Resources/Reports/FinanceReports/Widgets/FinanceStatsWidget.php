<?php

namespace App\Filament\Tenant\Resources\Reports\FinanceReports\Widgets;

use App\Filament\Tenant\Resources\Reports\FinanceReports\Pages\ListFinanceReports;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class FinanceStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListFinanceReports::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        $incomeQuery = clone $query;
        $totalIncome = $incomeQuery->whereIn('type', ['sale', 'revenue', 'cashin'])->sum('grand_total');

        $expenseQuery = clone $query;
        $totalExpense = $expenseQuery->whereIn('type', ['expense', 'cashout', 'refund', 'purchaseorder'])->sum('grand_total');

        $netBalance = $totalIncome - $totalExpense;

        return [
            Stat::make('Total Pemasukan', 'Rp ' . number_format((float)$totalIncome, 0, ',', '.'))
                ->description('Dari Penjualan & Kas Masuk')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Pengeluaran', 'Rp ' . number_format((float)$totalExpense, 0, ',', '.'))
                ->description('Dari Biaya & Kas Keluar')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Saldo Akhir (Net)', 'Rp ' . number_format((float)$netBalance, 0, ',', '.'))
                ->description('Pemasukan dikurangi Pengeluaran')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netBalance >= 0 ? 'success' : 'danger'),
        ];
    }
}