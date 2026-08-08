<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;
        $tenantId = $company->id;
        $currentUserId = $request->user()->id;

        $users = User::with(['role:id,name', 'outlet:id,name'])
            ->where('company_id', $tenantId)
            ->latest()
            ->get()
            ->map(function ($u) use ($currentUserId) {
                $data = $u->toArray();
                $data['role_name'] = $u->role->name ?? '-';
                $data['outlet_name'] = $u->outlet->name ?? 'Semua Cabang';
                // Hindari menghapus diri sendiri
                $data['can_delete'] = $u->id !== $currentUserId; 
                return $data;
            });

        // Hitung limit dari paket langganan
        $features = $company->subscriptionPlan?->features;
        if (is_string($features)) {
            $features = json_decode($features, true);
        }
        $maxUsers = data_get($features, 'limits.users');

        // Data pendukung untuk Dropdown Form
        $roles = Role::where('company_id', $tenantId)->get(['id', 'name']);
        $outlets = Outlet::where('company_id', $tenantId)->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $users,
            'roles' => $roles,
            'outlets' => $outlets,
            'max_users' => is_numeric($maxUsers) ? (int)$maxUsers : 9999,
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->user()->company ?? $request->user()->tenant;
        $tenantId = $company->id;

        /*
        |--------------------------------------------------------------------------
        | VALIDASI LIMIT LANGGANAN
        |--------------------------------------------------------------------------
        */
        $currentUsers = User::where('company_id', $tenantId)->count();
        $features = is_string($company->subscriptionPlan?->features) 
            ? json_decode($company->subscriptionPlan?->features, true) 
            : $company->subscriptionPlan?->features;
            
        $maxUsers = data_get($features, 'limits.users');

        if (is_numeric($maxUsers) && $currentUsers >= $maxUsers) {
            return response()->json([
                'success' => false,
                'message' => "Batas maksimal {$maxUsers} karyawan telah tercapai. Silakan upgrade paket."
            ], 403);
        }
        /*-------------------------------------------------------------------------*/

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'pin' => 'nullable|numeric|digits_between:4,10',
            'role_id' => 'required|exists:roles,id',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        $validated['company_id'] = $tenantId;
        $validated['user_type'] = 'tenant';
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json(['success' => true, 'message' => 'Karyawan berhasil ditambahkan.']);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $user = User::where('company_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'pin' => 'nullable|numeric|digits_between:4,10',
            'role_id' => 'required|exists:roles,id',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); 
        }

        $user->update($validated);

        return response()->json(['success' => true, 'message' => 'Data karyawan diperbarui.']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $user = User::where('company_id', $tenantId)->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri.'], 403);
        }
        
        $user->delete();

        return response()->json(['success' => true, 'message' => 'Karyawan berhasil dihapus.']);
    }
}