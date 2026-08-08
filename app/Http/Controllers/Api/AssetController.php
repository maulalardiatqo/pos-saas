<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = ($user->company ?? $user->tenant)->id;

        // Jika Owner lihat semua, jika kasir/staff lihat aset di outletnya saja
        $query = Asset::with('outlet:id,name')->where('company_id', $tenantId)->latest();
        
        $isOwner = method_exists($user, 'isOwner') ? $user->isOwner() : ($user->role_id == 1);
        if (!$isOwner) {
            $query->where('outlet_id', $user->outlet_id);
        }

        $assets = $query->get();
        $outlets = Outlet::where('company_id', $tenantId)->get(['id', 'name']);
        $accounts = Account::where('company_id', $tenantId)->where('is_active', true)->get(['id', 'name']);

        return response()->json([
            'success' => true, 
            'assets' => $assets,
            'outlets' => $outlets,
            'accounts' => $accounts
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets')->where('company_id', $tenantId)],
            'category' => 'required|string',
            'outlet_id' => 'required|exists:outlets,id',
            'purchase_date' => 'required|date',
            'purchase_price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
            
            // Kolom Virtual dari form
            'acquisition_type' => 'required|in:opening,purchase',
            'payment_method' => 'nullable|required_if:acquisition_type,purchase|string',
            'account_id' => 'nullable|required_if:acquisition_type,purchase|exists:accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $assetData = [
                'company_id' => $tenantId,
                'outlet_id' => $data['outlet_id'],
                'name' => $data['name'],
                'asset_code' => $data['asset_code'],
                'category' => $data['category'],
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => $data['purchase_price'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ];

            // 1. Eksekusi Jurnal Kas jika statusnya "Pembelian Baru"
            if ($data['acquisition_type'] === 'purchase') {
                $transaction = Transaction::create([
                    'company_id'         => $tenantId,
                    'outlet_id'          => $data['outlet_id'], 
                    'user_id'            => $request->user()->id,
                    'account_id'         => $data['account_id'], 
                    'in_out'             => 'out',      
                    'transaction_number' => 'AST-BUY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                    'type'               => 'asset_purchase', 
                    'status'             => 'completed',
                    'payment_method'     => $data['payment_method'],
                    'subtotal'           => $data['purchase_price'],
                    'grand_total'        => $data['purchase_price'],
                    'amount_paid'        => $data['purchase_price'],
                ]);

                $assetData['transaction_id'] = $transaction->id;

                // Potong Saldo Akun
                Account::where('id', $data['account_id'])->decrement('balance', $data['purchase_price']);
            }

            // 2. Buat Aset
            $asset = Asset::create($assetData);

            // 3. Buat Log
            AssetLog::create([
                'company_id'   => $tenantId,
                'asset_id'     => $asset->id,
                'user_id'      => $request->user()->id,
                'action_type'  => 'created',
                'to_outlet_id' => $asset->outlet_id,
                'remarks'      => 'Pendataan awal aset di sistem via Mobile.',
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Aset berhasil didaftarkan']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan aset: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $asset = Asset::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets')->where('company_id', $tenantId)->ignore($asset->id)],
            'category' => 'required|string',
            'purchase_date' => 'required|date',
            'purchase_price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        if ($asset->status !== $data['status']) {
            AssetLog::create([
                'company_id'  => $tenantId, 'asset_id' => $asset->id, 'user_id' => $request->user()->id,
                'action_type' => 'status_changed', 'remarks' => "Status berubah dari {$asset->status} menjadi {$data['status']}",
            ]);
        }

        $asset->update($data);
        return response()->json(['success' => true, 'message' => 'Data Aset berhasil diperbarui']);
    }

    // Fungsi Khusus: Mutasi (Pindah Cabang)
    public function move(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $asset = Asset::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'to_outlet_id' => 'required|exists:outlets,id|different:outlet_id',
            'remarks' => 'required|string',
        ]);

        AssetLog::create([
            'company_id'     => $tenantId,
            'asset_id'       => $asset->id,
            'user_id'        => $request->user()->id,
            'action_type'    => 'moved',
            'from_outlet_id' => $asset->outlet_id,
            'to_outlet_id'   => $data['to_outlet_id'],
            'remarks'        => $data['remarks'],
        ]);

        $asset->update(['outlet_id' => $data['to_outlet_id']]);

        return response()->json(['success' => true, 'message' => 'Aset berhasil dimutasi ke cabang lain']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        Asset::where('company_id', $tenantId)->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Aset berhasil dihapus']);
    }
}