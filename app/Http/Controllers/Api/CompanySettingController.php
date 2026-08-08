<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Perusahaan tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Perusahaan tidak ditemukan'], 404);
        }

        // Cek Hak Akses Owner
        $isOwner = method_exists($request->user(), 'isOwner') ? $request->user()->isOwner() : ($request->user()->role_id == 1);
        if (!$isOwner) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat mengubah pengaturan ini.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'pos_with_img' => 'required|boolean',
            'nota_size' => 'required|in:58mm,80mm,A4',
            'is_nota_logo' => 'required|boolean',
            'is_loyalty_enabled' => 'required|boolean',
            'loyalty_spend_amount' => 'required|numeric|min:0',
            'loyalty_point_earned' => 'required|integer|min:0',
            'loyalty_point_value' => 'required|numeric|min:0',
        ]);

        if ($request->hasFile('logo')) {
            // Hapus logo lama jika ada
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $company->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan Perusahaan berhasil diperbarui',
            'logo_url' => $company->logo ? asset('storage/' . $company->logo) : null
        ]);
    }
}