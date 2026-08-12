<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockMovement;
use App\Models\Stock; // <-- IMPORT MODEL STOCK BARU KITA
use App\Models\Outlet;
use App\Models\Product;
use App\Observers\StockTransferObserver; // <-- IMPORT OBSERVER MUTASI STOK
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

            $transfers = StockTransfer::with(['fromOutlet:id,name', 'toOutlet:id,name', 'items.product:id,name'])
                ->where('company_id', $tenantId)
                ->latest()
                ->get()
                ->map(function ($transfer) {
                    $data = $transfer->toArray();
                    $data['from_outlet'] = $transfer->fromOutlet; // Paksa jadi snake_case
                    $data['to_outlet']   = $transfer->toOutlet;   // Paksa jadi snake_case
                    return $data;
                });

            $outlets = Outlet::where('company_id', $tenantId)->get(['id', 'name']);
            
            $products = Product::where('company_id', $tenantId)
                ->where('item_type', 'goods')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'transfers' => $transfers,
                'outlets' => $outlets,
                'products' => $products,
            ], 200);

        } catch (\Exception $e) {
            Log::error('API StockTransfer Index Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Backend Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getStock(Request $request)
    {
        try {
            $request->validate([
                'outlet_id' => 'required|string',
                'product_id' => 'required|string',
            ]);

            // PERBAIKAN: Cek stok langsung dari tabel stocks
            $stockRecord = Stock::where('product_id', $request->product_id)
                ->where('outlet_id', $request->outlet_id)
                ->first();

            return response()->json([
                'success' => true,
                'stock' => $stockRecord ? (float) $stockRecord->qty : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'stock' => 0]);
        }
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $request->validate([
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.1',
        ]);

        // PERBAIKAN: Validasi ketersediaan stok fisik di outlet asal dari tabel stocks
        foreach ($request->items as $item) {
            $stockRecord = Stock::where('product_id', $item['product_id'])
                ->where('outlet_id', $request->from_outlet_id)
                ->first();
            $available = $stockRecord ? (float) $stockRecord->qty : 0;

            if ($item['quantity'] > $available) {
                $product = Product::find($item['product_id']);
                return response()->json([
                    'success' => false,
                    'message' => "Stok {$product->name} tidak cukup (Tersedia: {$available}).",
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $transfer = StockTransfer::create([
                'company_id' => $tenantId,
                'reference_number' => 'TRF-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'transfer_date' => $request->transfer_date,
                'status' => 'draft',
                'notes' => $request->notes,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Mutasi berhasil dibuat (Draft).']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
            $transfer = StockTransfer::where('company_id', $tenantId)->findOrFail($id);

            if ($transfer->status === 'completed') {
                return response()->json(['success' => false, 'message' => 'Dokumen sudah diselesaikan, tidak bisa diubah.'], 400);
            }

            $request->validate([
                'from_outlet_id' => 'required|exists:outlets,id',
                'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
                'transfer_date' => 'required|date',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.1',
            ]);

            // PERBAIKAN: Validasi ketersediaan stok dari tabel stocks
            foreach ($request->items as $item) {
                $stockRecord = Stock::where('product_id', $item['product_id'])
                    ->where('outlet_id', $request->from_outlet_id)
                    ->first();
                $available = $stockRecord ? (float) $stockRecord->qty : 0;

                if ($item['quantity'] > $available) {
                    $product = Product::find($item['product_id']);
                    return response()->json([
                        'success' => false,
                        'message' => "Stok produk {$product->name} tidak cukup (Tersedia: {$available}).",
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Update Header
            $transfer->update([
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'transfer_date' => $request->transfer_date,
                'notes' => $request->notes,
            ]);

            // Hapus items lama & insert items baru
            $transfer->items()->delete();

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Mutasi berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function complete(Request $request, $id)
    {
        try {
            $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
            $transfer = StockTransfer::where('company_id', $tenantId)->findOrFail($id);

            if ($transfer->status === 'completed') {
                return response()->json(['success' => false, 'message' => 'Sudah diselesaikan.'], 400);
            }

            DB::beginTransaction();

            // 1. Ubah status dokumen
            $transfer->update(['status' => 'completed']);

            // 2. PERBAIKAN: Panggil Observer untuk memindahkan stok riil antar gudang
            (new StockTransferObserver())->processStockMovements($transfer);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Stok berhasil dipindahkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    public function destroy(Request $request, $id)
    {
        try {
            $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
            $transfer = StockTransfer::where('company_id', $tenantId)->findOrFail($id);

            if ($transfer->status === 'completed') {
                return response()->json(['success' => false, 'message' => 'Tidak bisa dihapus.'], 400);
            }

            $transfer->delete();
            return response()->json(['success' => true, 'message' => 'Dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error.'], 500);
        }
    }
}