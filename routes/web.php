<?php

use Illuminate\Support\Facades\Route;
use App\Models\Transaction;
use App\Models\Company;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect('/app/login'); 
})->name('login');
Route::get('/pos/receipt/{id}', function ($id) {
    $transaction = Transaction::with(['items', 'customer'])->findOrFail($id);
    
    $company = Company::find($transaction->company_id);
    
    return view('pos.receipt', compact('transaction', 'company'));
})->name('pos.receipt');
Route::get('/sales-invoice/{id}/print', function ($id) {
        $transaction = Transaction::with(['items.uom', 'customer', 'company', 'outlet'])->findOrFail($id);
        
        // Memanggil file view blade
        return view('print.sales-invoice', compact('transaction'));
    })->name('sales-invoice.print');