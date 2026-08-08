<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        // Urutkan berdasarkan min_points agar rapi dari tier terendah ke tertinggi
        $memberships = Membership::where('company_id', $tenantId)
            ->orderBy('min_points', 'asc')
            ->get();

        return response()->json([
            'success' => true, 
            'memberships' => $memberships
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('memberships')->where('company_id', $tenantId)
            ],
            'min_points' => 'required|integer|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'required|boolean',
        ]);

        $data['company_id'] = $tenantId;

        Membership::create($data);

        return response()->json(['success' => true, 'message' => 'Membership berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $membership = Membership::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('memberships')->where('company_id', $tenantId)->ignore($membership->id)
            ],
            'min_points' => 'required|integer|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'required|boolean',
        ]);

        $membership->update($data);

        return response()->json(['success' => true, 'message' => 'Membership berhasil diperbarui']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $membership = Membership::where('company_id', $tenantId)->findOrFail($id);
        
        // Cek jika ada pelanggan yang memakai membership ini
        if ($membership->customers()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Gagal! Membership ini sedang digunakan oleh pelanggan.'], 400);
        }

        $membership->delete();

        return response()->json(['success' => true, 'message' => 'Membership berhasil dihapus']);
    }
}