<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function getHistory(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? filament()->getTenant()->id ?? $user->company->id;

        $query = Transaction::with(['customer:id,name', 'outlet:id,name', 'user:id,name', 'items.product:id,name', 'items.uom:id,name'])
            ->where('company_id', $companyId);

        // 1. FILTER SCOPE (Role based)
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner) {
            $query->where('outlet_id', $user->outlet_id);
        } else {
            if ($request->filled('outlet_id')) {
                $query->where('outlet_id', $request->outlet_id);
            }
        }

        // 2. FILTER SEARCH (No Nota)
        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%');
        }

        // 3. FILTER STATUS
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 4. FILTER DATES
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Ambil data terbaru di atas
        $transactions = $query->latest('created_at')->limit(50)->get();

        return response()->json([
            'success' => true,
            'is_owner' => $isOwner,
            'data' => $transactions
        ]);
    }

    public function voidTransaction(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? filament()->getTenant()->id ?? $user->company->id;
        
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $record = Transaction::with('items.product')->where('company_id', $companyId)->findOrFail($id);

        // Jika bukan owner, hanya boleh void transaksi di outletnya sendiri
        if (!$isOwner && $record->outlet_id !== $user->outlet_id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak membatalkan transaksi dari cabang lain.'], 403);
        }

        if ($record->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Transaksi sudah dibatalkan sebelumnya.'], 400);
        }

        try {
            DB::transaction(function () use ($record, $companyId) {
                $outletId = $record->outlet_id;

                // 1. Ubah status nota menjadi voided (cancelled)
                $record->update(['status' => 'cancelled']);

                // 2. Kembalikan stok untuk barang fisik
                foreach ($record->items as $item) {
                    $isService = ($item->product?->item_type ?? 'goods') === 'service';
                    
                    if (!$isService) {
                        $lastMovement = \App\Models\StockMovement::where('product_id', $item->product_id)
                            ->where('outlet_id', $outletId)
                            ->latest()
                            ->first();

                        $balanceBefore = $lastMovement ? (float) $lastMovement->balance_after : 0.00;
                        $balanceAfter = $balanceBefore + (float) $item->base_qty;

                        \App\Models\StockMovement::create([
                            'company_id' => $companyId,
                            'outlet_id' => $outletId,
                            'product_id' => $item->product_id,
                            'type' => 'void', 
                            'reference_type' => Transaction::class,
                            'reference_id' => $record->id,
                            'quantity' => $item->base_qty,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'remarks' => 'VOID Nota POS: ' . $record->transaction_number,
                        ]);
                    }
                }

                // 3. Tarik poin dari pelanggan
                if ($record->customer_id) {
                    // (Sesuaikan nilai pembagi poin dengan setting loyalty Anda, default fallback 10000)
                    $company = \App\Models\Company::find($companyId);
                    $spendAmount = $company->loyalty_spend_amount > 0 ? $company->loyalty_spend_amount : 10000;
                    
                    $earnedPoints = floor($record->grand_total / $spendAmount); 
                    if ($earnedPoints > 0) {
                        \App\Models\PointHistory::create([
                            'company_id' => $companyId,
                            'customer_id' => $record->customer_id,
                            'type' => 'redeem', 
                            'amount' => $earnedPoints,
                            'reference_id' => $record->transaction_number,
                            'description' => 'Penarikan poin otomatis (VOID)',
                        ]);
                        \App\Models\Customer::where('id', $record->customer_id)->decrement('points_balance', $earnedPoints);
                    }
                }
                
                if ($record->pos_session_id) {
                    $session = \App\Models\PosSession::find($record->pos_session_id);
                    if ($session && $session->status === 'open') {
                        $session->decrement('total_sales', $record->grand_total);
                        if ($record->payment_method === 'cash') {
                            $session->decrement('total_cash_sales', $record->grand_total);
                        }
                    }
                }
                if ($record->account_id) {
                    $account = \App\Models\Account::find($record->account_id);
                    if ($account) {
                        $netAmount = $record->grand_total - (float) $record->admin_fee;
                        $account->decrement('balance', $netAmount);
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'Transaksi berhasil dibatalkan (VOID). Stok dan Saldo telah dikembalikan.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membatalkan transaksi: ' . $e->getMessage()], 500);
        }
    }
}