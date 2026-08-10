<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\PurchaseOrderController;

Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handleNotification']);

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/pos/data', [PosController::class, 'getData']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/app-access', [PosController::class, 'getAppAccess']);
    Route::get('/master/brands', [MasterDataController::class, 'getBrands']);
    Route::post('/master/brands', [MasterDataController::class, 'storeBrand']);
    Route::put('/master/brands/{id}', [MasterDataController::class, 'updateBrand']);
    Route::delete('/master/brands/{id}', [MasterDataController::class, 'deleteBrand']);

    //Categories
    Route::get('/master/categories', [MasterDataController::class, 'getCategories']);
    Route::post('/master/categories', [MasterDataController::class, 'storeCategory']);
    Route::put('/master/categories/{id}', [MasterDataController::class, 'updateCategory']);
    Route::delete('/master/categories/{id}', [MasterDataController::class, 'deleteCategory']);
    // UOMS
    Route::get('/master/uoms', [MasterDataController::class, 'getUoms']);
    Route::post('/master/uoms', [MasterDataController::class, 'storeUom']);
    Route::put('/master/uoms/{id}', [MasterDataController::class, 'updateUom']);
    Route::delete('/master/uoms/{id}', [MasterDataController::class, 'deleteUom']);

    // product
    Route::get('/master/products', [MasterDataController::class, 'getProducts']);
    Route::post('/master/products', [MasterDataController::class, 'storeProduct']);
    Route::put('/master/products/{id}', [MasterDataController::class, 'updateProduct']);
    Route::delete('/master/products/{id}', [MasterDataController::class, 'deleteProduct']);

    Route::get('/master/accounts/outlets', [MasterDataController::class, 'getOutletsForDropdown']);
    Route::get('/master/accounts', [MasterDataController::class, 'getAccounts']);
    Route::post('/master/accounts', [MasterDataController::class, 'storeAccount']);
    Route::put('/master/accounts/{id}', [MasterDataController::class, 'updateAccount']);
    Route::delete('/master/accounts/{id}', [MasterDataController::class, 'deleteAccount']);


    Route::get('/transactions/history', [TransactionController::class, 'getHistory']);
    Route::post('/transactions/{id}/void', [TransactionController::class, 'voidTransaction']);

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'history']);    
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']); 
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    Route::put('/purchase-orders/{id}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchase-orders/{id}', [PurchaseOrderController::class, 'destroy']);

    Route::apiResource('opening-balances', \App\Http\Controllers\Api\OpeningBalanceController::class);
    Route::apiResource('expenses', \App\Http\Controllers\Api\ExpenseController::class);
    Route::apiResource('revenues', \App\Http\Controllers\Api\RevenueController::class);

    // --- ROUTES SUPPLIER ---
    Route::get('/master/suppliers', [\App\Http\Controllers\Api\MasterDataController::class, 'getSuppliers']);
    Route::post('/master/suppliers', [\App\Http\Controllers\Api\MasterDataController::class, 'storeSupplier']);
    Route::put('/master/suppliers/{id}', [\App\Http\Controllers\Api\MasterDataController::class, 'updateSupplier']);
    Route::delete('/master/suppliers/{id}', [\App\Http\Controllers\Api\MasterDataController::class, 'deleteSupplier']);

    // --- ROUTES CUSTOMER ---
    Route::get('/master/customers', [\App\Http\Controllers\Api\MasterDataController::class, 'getCustomers']);
    Route::post('/master/customers', [\App\Http\Controllers\Api\MasterDataController::class, 'storeCustomer']);
    Route::put('/master/customers/{id}', [\App\Http\Controllers\Api\MasterDataController::class, 'updateCustomer']);
    Route::delete('/master/customers/{id}', [\App\Http\Controllers\Api\MasterDataController::class, 'deleteCustomer']);

    Route::apiResource('stock-adjustments', \App\Http\Controllers\Api\StockAdjustmentController::class);

    Route::get('/stock-movements', [\App\Http\Controllers\Api\StockMovementController::class, 'index']);
    Route::get('/stock-balances', [\App\Http\Controllers\Api\StockBalanceController::class, 'index']);

    Route::post('/settings/loyalty', [\App\Http\Controllers\Api\PosController::class, 'updateLoyaltySettings']);

    Route::apiResource('loyalty-rewards', \App\Http\Controllers\Api\LoyaltyRewardController::class);

    Route::apiResource('gift-cards', \App\Http\Controllers\Api\GiftCardController::class);
    Route::apiResource('memberships', \App\Http\Controllers\Api\MembershipController::class);
    Route::apiResource('vouchers', \App\Http\Controllers\Api\VoucherController::class);

    Route::post('assets/{id}/move', [\App\Http\Controllers\Api\AssetController::class, 'move']);
    Route::apiResource('assets', \App\Http\Controllers\Api\AssetController::class);

    Route::post('/logout', function (\Illuminate\Http\Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Berhasil logout']);
    });

    Route::get('/settings/company', [\App\Http\Controllers\Api\CompanySettingController::class, 'show']);
    Route::post('/settings/company', [\App\Http\Controllers\Api\CompanySettingController::class, 'update']);

    Route::get('stock-transfers/stock', [\App\Http\Controllers\Api\StockTransferController::class, 'getStock']);
    Route::post('stock-transfers/{id}/complete', [\App\Http\Controllers\Api\StockTransferController::class, 'complete']);
    Route::apiResource('stock-transfers', \App\Http\Controllers\Api\StockTransferController::class);

    Route::apiResource('outlets', \App\Http\Controllers\Api\OutletController::class);
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    // reports
    Route::get('reports/finance', [\App\Http\Controllers\Api\FinanceReportController::class, 'index']);
    Route::get('reports/sales', [\App\Http\Controllers\Api\SalesReportController::class, 'index']);
    Route::get('reports/products', [\App\Http\Controllers\Api\ProductReportController::class, 'index']);
});