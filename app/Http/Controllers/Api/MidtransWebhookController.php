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
        $paymentType = $payload['payment_type'] ?? 'unknown';
        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            
            // PENCEGAHAN EKSEKUSI GANDA (Idempotency)
            if ($transaction->status !== 'completed') {
                DB::transaction(function () use ($transaction, $grossAmount, $paymentType) {
                    
                    $adminFee = 0;
                    $gross = (float) $grossAmount;

                    if ($paymentType === 'qris' || $paymentType === 'gopay') {
                        $adminFee = $gross * 0.007; // Potongan QRIS / GoPay = 0.7%
                    } elseif ($paymentType === 'bank_transfer' || $paymentType === 'echannel') {
                        $adminFee = 4000; // Potongan Virtual Account = Flat Rp 4.000
                    } elseif ($paymentType === 'shopeepay') {
                        $adminFee = $gross * 0.02; // Potongan ShopeePay = 2%
                    } elseif ($paymentType === 'credit_card') {
                        $adminFee = ($gross * 0.02) + 2000; // Kartu Kredit = 2% + Rp 2.000
                    }
                    
                    // Pastikan tidak ada desimal pecah
                    $adminFee = round($adminFee);
                    
                    $netAmount = $gross - $adminFee;

                    $transaction->update([
                        'status' => 'completed',
                        'admin_fee' => $adminFee, 
                    ]);
                    
                    Account::where('id', $transaction->account_id)->increment('balance', $netAmount);
                });
            }

            Log::info("Webhook Midtrans: Transaksi {$orderId} Lunas. Gross: {$grossAmount}, Fee: Potongan dihitung.");

        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $transaction->update(['status' => 'failed']);
            Log::info("Webhook Midtrans: Transaksi {$orderId} dibatalkan/kadaluarsa.");
        }

        return response()->json(['message' => 'Notifikasi Midtrans berhasil diproses']);
    }
}