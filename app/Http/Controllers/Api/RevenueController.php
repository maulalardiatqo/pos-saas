<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $query = Transaction::with(['account:id,name', 'outlet:id,name'])
            ->where('company_id', $companyId)
            ->where('type', 'revenue');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner && $user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        }

        $revenues = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $revenues]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $request->validate([
            'outlet_id'   => 'required',
            'account_id'  => 'required',
            'notes'       => 'required|string|max:255',
            'grand_total' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $revenue = Transaction::create([
                'company_id'         => $companyId,
                'user_id'            => $user->id,
                'transaction_number' => 'REV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'type'               => 'revenue',
                'in_out'             => 'in',
                'status'             => 'completed',
                'payment_method'     => 'cash',
                'outlet_id'          => $request->outlet_id,
                'account_id'         => $request->account_id,
                'notes'              => $request->notes,
                'grand_total'        => $request->grand_total,
                'amount_paid'        => $request->grand_total,
                'subtotal'           => $request->grand_total,
            ]);

            Account::where('id', $revenue->account_id)->increment('balance', $revenue->grand_total);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Pemasukan berhasil dicatat.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mencatat pemasukan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $request->validate([
            'outlet_id'   => 'required',
            'account_id'  => 'required',
            'notes'       => 'required|string|max:255',
            'grand_total' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $revenue = Transaction::where('company_id', $companyId)
                ->where('type', 'revenue')
                ->findOrFail($id);
            if ($revenue->account_id) {
                Account::where('id', $revenue->account_id)->decrement('balance', $revenue->grand_total);
            }

            $revenue->update([
                'outlet_id'   => $request->outlet_id,
                'account_id'  => $request->account_id,
                'notes'       => $request->notes,
                'grand_total' => $request->grand_total,
                'amount_paid' => $request->grand_total,
                'subtotal'    => $request->grand_total,
            ]);

            if ($revenue->account_id) {
                Account::where('id', $revenue->account_id)->increment('balance', $revenue->grand_total);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Pemasukan berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui pemasukan.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        DB::beginTransaction();
        try {
            $revenue = Transaction::where('company_id', $companyId)
                ->where('type', 'revenue')
                ->findOrFail($id);

            if ($revenue->account_id) {
                Account::where('id', $revenue->account_id)->decrement('balance', $revenue->grand_total);
            }

            $revenue->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Pemasukan dibatalkan, saldo telah ditarik kembali.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus pemasukan.'], 500);
        }
    }
}