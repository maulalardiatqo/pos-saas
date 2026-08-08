<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $vouchers = Voucher::where('company_id', $tenantId)->latest()->get();

        return response()->json([
            'success' => true, 
            'vouchers' => $vouchers
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => [
                'required', 'string', 'max:50', 'alpha_num',
                Rule::unique('vouchers')->where('company_id', $tenantId)
            ],
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ]);

        $data['company_id'] = $tenantId;
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        
        // Reset max_discount jika tipenya fixed
        if ($data['discount_type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        Voucher::create($data);

        return response()->json(['success' => true, 'message' => 'Voucher berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $voucher = Voucher::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => [
                'required', 'string', 'max:50', 'alpha_num',
                Rule::unique('vouchers')->where('company_id', $tenantId)->ignore($voucher->id)
            ],
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ]);

        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        if ($data['discount_type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        $voucher->update($data);

        return response()->json(['success' => true, 'message' => 'Voucher berhasil diperbarui']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $voucher = Voucher::where('company_id', $tenantId)->findOrFail($id);
        
        if ($voucher->used_count > 0) {
            return response()->json(['success' => false, 'message' => 'Gagal! Voucher sudah pernah digunakan.'], 400);
        }

        $voucher->delete();

        return response()->json(['success' => true, 'message' => 'Voucher berhasil dihapus']);
    }
}