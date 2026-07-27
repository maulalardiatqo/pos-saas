<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function handleNotification(Request $request)
    {
        $payload = $request->all();
        
        $orderId           = $payload['order_id'] ?? null;
        $statusCode        = $payload['status_code'] ?? null;
        $grossAmount       = $payload['gross_amount'] ?? null;
        $signatureKey      = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        // 1. Validasi Payload
        if (!$orderId) {
            return response()->json(['message' => 'Payload tidak valid'], 400);
        }

        // 2. Cari Transaksi
        $transaction = Transaction::where('transaction_number', $orderId)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        if ($transaction->status === 'completed') {
            return response()->json(['message' => 'Transaksi sudah diproses sebelumnya']);
        }

        $company = Company::find($transaction->company_id);
        if (!$company) {
            return response()->json(['message' => 'Tenant tidak ditemukan'], 404);
        }

        $serverKey         = $company->midtrans_server_key;
        $computedSignature = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);

        if ($computedSignature !== $signatureKey) {
            return response()->json(['message' => 'Signature Key tidak sah / Akses ditolak'], 403);
        }

        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'completed']);
                
                Account::where('id', $transaction->account_id)->increment('balance', $transaction->grand_total);
            });

            Log::info("Webhook Midtrans: Transaksi {$orderId} berhasil diubah menjadi Completed.");

        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $transaction->update(['status' => 'failed']);
            Log::info("Webhook Midtrans: Transaksi {$orderId} dibatalkan/kadaluarsa.");
        }

        return response()->json(['message' => 'Notifikasi Midtrans berhasil diproses']);
    }
}