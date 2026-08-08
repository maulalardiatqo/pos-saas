<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;
        $tenantId = $company->id;

        $outlets = Outlet::where('company_id', $tenantId)
            ->latest()
            ->get();

        $features = $company->subscriptionPlan?->features;
        if (is_string($features)) {
            $features = json_decode($features, true);
        }
        $maxOutlets = data_get($features, 'limits.outlets');

        return response()->json([
            'success' => true,
            'data' => $outlets,
            'max_outlets' => is_numeric($maxOutlets) ? (int)$maxOutlets : 9999, 
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;
        $tenantId = $company->id;

        /*
        |--------------------------------------------------------------------------
        | VALIDASI LIMIT LANGGANAN (Mencegah Pembuatan Outlet Melebihi Batas)
        |--------------------------------------------------------------------------
        */
        $currentOutlets = Outlet::where('company_id', $tenantId)->count();
        
        // Ambil fitur dari plan (decode JSON jika formatnya masih string)
        $features = $company->subscriptionPlan?->features;
        if (is_string($features)) {
            $features = json_decode($features, true);
        }
        
        $maxOutlets = data_get($features, 'limits.outlets');

        if (is_numeric($maxOutlets) && $currentOutlets >= $maxOutlets) {
            return response()->json([
                'success' => false,
                'message' => "Batas maksimal {$maxOutlets} cabang telah tercapai. Silakan upgrade paket."
            ], 403); // 403 Forbidden
        }
        /*-------------------------------------------------------------------------*/

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:store,warehouse,canvas',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['company_id'] = $tenantId;
        // Generate Code persis seperti di Filament
        $validated['code'] = 'OUT-' . date('Ymd-His') . '-' . strtoupper(Str::random(4));
        $validated['is_active'] = $request->is_active ?? true;

        $outlet = Outlet::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Outlet berhasil ditambahkan.',
            'data' => $outlet
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $outlet = Outlet::where('company_id', $tenantId)->findOrFail($id);

        // Edit tidak perlu cek limit karena tidak menambah jumlah outlet
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:store,warehouse,canvas',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $outlet->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Outlet berhasil diperbarui.',
            'data' => $outlet
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $outlet = Outlet::where('company_id', $tenantId)->findOrFail($id);

        // Proteksi: Tidak boleh menghapus outlet utama (MAIN)
        if ($outlet->code === 'MAIN') {
            return response()->json([
                'success' => false,
                'message' => 'Outlet utama (MAIN) tidak dapat dihapus.'
            ], 403);
        }
        
        $outlet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Outlet berhasil dihapus.'
        ]);
    }
}