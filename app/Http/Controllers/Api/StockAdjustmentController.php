<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Observers\StockAdjustmentObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAdjustmentController extends Controller
{
    private function getCompanyId($user)
    {
        return $user->company_id ?? $user->tenant_id ?? $user->company->id;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $this->getCompanyId($user);

        $query = StockAdjustment::with(['outlet:id,name', 'user:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc');

        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner && $user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request->user());
        $adjustment = StockAdjustment::with(['items.product', 'outlet:id,name'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $adjustment]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = $this->getCompanyId($user);

        $request->validate([
            'outlet_id' => 'required',
            'date'      => 'required|date',
            'status'    => 'required|in:draft,completed',
            'reason'    => 'nullable|string',
            'items'     => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.uom_id'     => 'required', 
            'items.*.type'       => 'required|in:addition,deduction',
            'items.*.quantity'   => 'required|numeric|min:0.1',
        ]);

        DB::beginTransaction();
        try {
            // 1. Buat Header
            $adjustment = StockAdjustment::create([
                'company_id'      => $companyId,
                'outlet_id'       => $request->outlet_id,
                'user_id'         => $user->id,
                'document_number' => 'ADJ-' . strtoupper(Str::random(8)),
                'date'            => $request->date,
                'status'          => $request->status,
                'reason'          => $request->reason,
            ]);

            // 2. Buat Items
            $itemsData = [];
            foreach ($request->items as $item) {
                $itemsData[] = [
                    'id'                  => (string) Str::ulid(),
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id'          => $item['product_id'],
                    'uom_id'              => $item['uom_id'],
                    'type'                => $item['type'],
                    'quantity'            => $item['quantity'],
                    'remarks'             => $item['remarks'] ?? null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
            DB::table('stock_adjustment_items')->insert($itemsData);

            // 3. TRIGGER OBSERVER JIKA STATUS COMPLETED
            if ($adjustment->status === 'completed') {
                (new StockAdjustmentObserver())->processStockMovements($adjustment);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penyesuaian stok berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request->user());
        $adjustment = StockAdjustment::where('company_id', $companyId)->findOrFail($id);

        if ($adjustment->status === 'completed' || $adjustment->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Dokumen yang sudah selesai/batal tidak bisa diedit.'], 400);
        }

        $request->validate([
            'outlet_id' => 'required',
            'date'      => 'required|date',
            'status'    => 'required|in:draft,completed',
            'items'     => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $adjustment->update([
                'outlet_id' => $request->outlet_id,
                'date'      => $request->date,
                'status'    => $request->status,
                'reason'    => $request->reason,
            ]);

            $adjustment->items()->delete();

            $itemsData = [];
            foreach ($request->items as $item) {
                $itemsData[] = [
                    'id'                  => (string) Str::ulid(),
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id'          => $item['product_id'],
                    'uom_id'              => $item['uom_id'],
                    'type'                => $item['type'],
                    'quantity'            => $item['quantity'],
                    'remarks'             => $item['remarks'] ?? null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
            DB::table('stock_adjustment_items')->insert($itemsData);

            // TRIGGER OBSERVER JIKA STATUS DIUBAH DARI DRAFT KE COMPLETED
            if ($adjustment->status === 'completed') {
                (new StockAdjustmentObserver())->processStockMovements($adjustment);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penyesuaian stok berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request->user());
        $adjustment = StockAdjustment::where('company_id', $companyId)->findOrFail($id);

        if ($adjustment->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Dokumen yang sudah memotong stok tidak bisa dihapus.'], 400);
        }

        $adjustment->delete(); 

        return response()->json(['success' => true, 'message' => 'Dokumen penyesuaian berhasil dihapus.']);
    }
}