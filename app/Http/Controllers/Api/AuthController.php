<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Cari user berdasarkan email
        $user = User::with(['role', 'company', 'outlet'])->where('email', $request->email)->first();

        // 3. Cek password & keberadaan user
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau Password salah.'
            ], 401);
        }

        // 4. Pastikan hanya user toko (tenant) yang bisa login ke POS Mobile
        // Karena platform/admin tidak butuh masuk ke mesin kasir
        if (! $user->isTenant()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya akun Kasir/Owner toko yang diizinkan.'
            ], 403);
        }

        // 5. Buat Token Sanctum
        // (Optional: hapus token lama jika ingin 1 device saja -> $user->tokens()->delete();)
        $token = $user->createToken('mobile-pos-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role?->code ?? 'unknown',
                'company_id' => $user->company_id,
                'outlet_id'  => $user->outlet_id,
                'outlet_name'=> $user->outlet?->name,
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil'
        ]);
    }
}