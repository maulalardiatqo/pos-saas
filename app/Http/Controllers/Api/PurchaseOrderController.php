<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\TransactionItem;
use App\Observers\PurchaseOrderObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        // Pastikan kita mendapatkan ID Perusahaan (Tenant) yang benar via API
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;
        
        $request->validate([
            'items' => 'required|array|min:1',
            'grand_total' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // 1. Simpan Header PO
            $po = PurchaseOrder::create([
                'company_id'     => $companyId,
                'outlet_id'      => $request->outlet_id ?? $user->outlet_id,
                'supplier_id'    => $request->supplier_id,
                'user_id'        => $user->id,
                'account_id'     => $request->account_id,
                'payment_method' => $request->payment_method ?? 'cash',
                'status'         => $request->status ?? 'completed', 
                'in_out'         => 'out', 
                'subtotal'       => $request->subtotal ?? $request->grand_total,
                'grand_total'    => $request->grand_total,
                'amount_paid'    => $request->amount_paid ?? $request->grand_total,
            ]);

            foreach ($request->items as $item) {
                TransactionItem::create([
                    'company_id'        => $companyId,
                    'transaction_id'    => $po->id,
                    'product_id'        => $item['product_id'],
                    'uom_id'            => $item['uom_id'],
                    'qty'               => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'base_qty'          => $item['base_qty'] ?? $item['qty'], 
                    
                    'cost_price'        => $item['cost_price'], 
                    'selling_price'     => 0,                   
                    'subtotal'          => $item['qty'] * $item['cost_price'],
                ]);
            }

            // =====================================================================
            // 3. TRIGGER OBSERVER SECARA MANUAL (Stok & HPP akan diproses di sini)
            // =====================================================================
            if ($po->status === 'completed') {
                (new PurchaseOrderObserver())->processStockMovements($po);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Pembelian (PO) berhasil dicatat! Saldo dan Stok telah diperbarui.']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mencatat PO: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $po = PurchaseOrder::with([
                'supplier:id,name,phone', 
                'outlet:id,name', 
                'account:id,name',
                'items.product:id,name,sku', 
            ])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $po]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $po = PurchaseOrder::where('company_id', $companyId)->findOrFail($id);
        
        // Cukup panggil delete(). Observer (deleting) yang kita buat sebelumnya 
        // akan otomatis bekerja menarik kembali stok dan saldo rekening.
        $po->delete();

        return response()->json(['success' => true, 'message' => 'PO berhasil dihapus. Stok dan Saldo telah dikembalikan.']);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $po = PurchaseOrder::where('company_id', $companyId)->findOrFail($id);

        // [PENGAMANAN] Jika status sudah completed, tidak boleh diubah itemnya
        // Untuk mengubah, user harus menghapus (void) lalu membuat PO baru.
        if ($po->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'PO yang sudah selesai (Completed) dan masuk ke gudang tidak dapat diubah.'], 403);
        }

        try {
            DB::beginTransaction();

            $po->update([
                'supplier_id'    => $request->supplier_id,
                'status'         => $request->status,
                'payment_method' => $request->payment_method,
                'account_id'     => $request->payment_method != 'credit' ? $request->account_id : null,
                'subtotal'       => $request->subtotal,
                'discount'       => $request->discount,
                'tax'            => $request->tax,
                'grand_total'    => $request->grand_total,
                'amount_paid'    => $request->amount_paid,
            ]);
            
            // Hapus item lama, ganti yang baru
            $po->items()->delete(); 

            foreach ($request->items as $item) {
                \App\Models\TransactionItem::create([
                    'company_id'        => $companyId,
                    'transaction_id'    => $po->id,
                    'product_id'        => $item['product_id'],
                    'uom_id'            => $item['uom_id'],
                    'qty'               => $item['qty'],
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'base_qty'          => $item['base_qty'] ?? $item['qty'], 
                    'cost_price'        => $item['cost_price'],
                    'selling_price'     => 0, 
                    'subtotal'          => $item['qty'] * $item['cost_price'],
                ]);
            }

            // Jika awalnya Draft lalu di-update menjadi Completed dari Mobile
            if ($request->status === 'completed') {
                (new PurchaseOrderObserver())->processStockMovements($po);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'PO berhasil diperbarui!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengupdate PO: ' . $e->getMessage()], 500);
        }
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;
        
        $isOwner = false;
        if (method_exists($user, 'isOwner')) {
            $isOwner = $user->isOwner();
        } elseif (isset($user->role_id) && clone $user->role_id == 1) {
            $isOwner = true;
        }

        $query = PurchaseOrder::with(['supplier:id,name', 'outlet:id,name'])
            ->where('company_id', $companyId);
            
        if (!$isOwner) {
            $query->where('outlet_id', $user->outlet_id);
        }

        $pos = $query->latest('created_at')->limit(50)->get();

        return response()->json(['success' => true, 'data' => $pos]);
    }
}