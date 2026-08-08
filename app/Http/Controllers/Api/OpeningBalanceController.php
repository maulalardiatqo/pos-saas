<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningBalanceController extends Controller
{
    // Helper Proteksi Khusus Owner
    private function checkOwnerAccess($user)
    {
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner) {
            abort(403, "Akses ditolak: Menu ini khusus Pemilik Usaha (Owner).");
        }
        return $user->company_id ?? $user->tenant_id ?? $user->company->id;
    }

    public function index(Request $request)
    {
        $companyId = $this->checkOwnerAccess($request->user());

        $balances = Transaction::with(['account:id,name', 'outlet:id,name'])
            ->where('company_id', $companyId)
            ->where('type', 'opening_balance')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function store(Request $request)
    {
        $companyId = $this->checkOwnerAccess($request->user());

        $request->validate([
            'outlet_id' => 'required',
            'account_id' => 'required',
            'amount_paid' => 'required|numeric|min:0',
        ]);

        $ob = Transaction::create([
            'company_id' => $companyId,
            'transaction_number' => 'OB-' . date('Ymd-His'),
            'type' => 'opening_balance',
            'in_out' => 'in',
            'status' => 'completed',
            'payment_method' => 'cash',
            'outlet_id' => $request->outlet_id,
            'account_id' => $request->account_id,
            'user_id' => $request->user()->id,
            'grand_total' => $request->amount_paid,
            'amount_paid' => $request->amount_paid,
            'subtotal' => $request->amount_paid,
        ]);

        return response()->json(['success' => true, 'message' => 'Saldo Awal berhasil ditambahkan.', 'data' => $ob]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->checkOwnerAccess($request->user());
        
        $ob = Transaction::where('company_id', $companyId)
            ->where('type', 'opening_balance')
            ->findOrFail($id);

        $request->validate([
            'outlet_id' => 'required',
            'account_id' => 'required',
            'amount_paid' => 'required|numeric|min:0',
        ]);

        $ob->update([
            'outlet_id' => $request->outlet_id,
            'account_id' => $request->account_id,
            'grand_total' => $request->amount_paid,
            'amount_paid' => $request->amount_paid,
            'subtotal' => $request->amount_paid,
        ]);

        return response()->json(['success' => true, 'message' => 'Saldo Awal berhasil diperbarui.']);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->checkOwnerAccess($request->user());
        
        $ob = Transaction::where('company_id', $companyId)
            ->where('type', 'opening_balance')
            ->findOrFail($id);

        $ob->delete();

        return response()->json(['success' => true, 'message' => 'Saldo Awal berhasil dihapus.']);
    }
}