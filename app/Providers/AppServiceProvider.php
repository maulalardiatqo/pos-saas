<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Company;
use App\Observers\CompanyObserver;
use App\Models\StockAdjustment;
use App\Observers\StockAdjustmentObserver;
use App\Observers\OutletObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PointHistoryObserver;

use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PointHistory;
use Laravel\Sanctum\Sanctum; 
use App\Models\PersonalAccessToken;
use App\Models\StockTransfer;
use App\Observers\StockTransferObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\App\Providers\Filament\AdminPanelProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Company::observe(CompanyObserver::class);
        StockAdjustment::observe(StockAdjustmentObserver::class);
        Outlet::observe(OutletObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PointHistory::observe(PointHistoryObserver::class);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        StockTransfer::observe(StockTransferObserver::class);
    }
}
