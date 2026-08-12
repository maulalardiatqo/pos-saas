<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\Stock; // <-- IMPORT MODEL STOCK BARU KITA
use Illuminate\Http\Request;

class StockBalanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        // 1. Tentukan Daftar Outlet yang bisa diakses & Target Outlet ID
        $outlets = [];
        $targetOutletId = null;

        if ($isOwner) {
            // Owner: Bisa lihat semua outlet. 
            // Jika ada request outlet_id, gunakan itu. Jika tidak, ambil outlet pertama perusahaan.
            $outlets = Outlet::where('company_id', $companyId)->select('id', 'name')->get();
            $targetOutletId = $request->outlet_id ?? $outlets->first()->id ?? null;
        } else {
            // Karyawan: Hanya outlet dia sendiri, tidak butuh dropdown.
            $targetOutletId = $user->outlet_id;
            if ($targetOutletId) {
                $outlets = Outlet::where('id', $targetOutletId)->select('id', 'name')->get();
            }
        }

        // Jika perusahaan belum memiliki outlet sama sekali
        if (!$targetOutletId) {
            return response()->json([
                'success' => true,
                'is_owner' => $isOwner,
                'outlets' => $outlets,
                'selected_outlet_id' => null,
                'data' => []
            ]);
        }

        // =====================================================================
        // 2. PERBAIKAN STOK: Membaca langsung dari tabel stocks (Super Ringan)
        // =====================================================================
        $latestStockSubquery = Stock::selectRaw('COALESCE(qty, 0)')
            ->whereColumn('product_id', 'products.id')
            ->where('outlet_id', $targetOutletId)
            ->limit(1);

        // 3. Ambil Produk (hanya barang fisik / goods)
        $products = Product::with(['category:id,name', 'baseUom:id,name', 'brand:id,name'])
            ->where('company_id', $companyId)
            ->where('item_type', 'goods')
            ->addSelect('products.*')
            ->selectSub($latestStockSubquery, 'current_stock')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'is_owner' => $isOwner,
            'outlets' => $outlets,
            'selected_outlet_id' => $targetOutletId,
            'data' => $products
        ]);
    }
}