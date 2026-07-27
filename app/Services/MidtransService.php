<?php

namespace App\Services;

use App\Models\Company;
use Midtrans\Config;
use Midtrans\Snap;
use Exception;

class MidtransService
{
    /**
     * Memuat konfigurasi Midtrans berdasarkan Tenant (Toko) yang sedang aktif
     */
    public static function configure(Company $company): void
    {
        if (empty($company->midtrans_server_key)) {
            throw new Exception("Toko ini belum mengonfigurasi pengaturan Midtrans.");
        }

        Config::$serverKey = $company->midtrans_server_key;
        Config::$clientKey = $company->midtrans_client_key;
        Config::$isProduction = $company->midtrans_is_production;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Fungsi untuk membuat transaksi (Snap Token) dari POS
     */
    public static function createTransaction(Company $company, array $transactionDetails, array $itemDetails = [], array $customerDetails = [])
    {
        self::configure($company);

        $params = [
            'transaction_details' => $transactionDetails, 
            'item_details'        => $itemDetails,
            'customer_details'    => $customerDetails,
            'enabled_payments'    => ['gopay', 'shopeepay', 'other_qris', 'bank_transfer'], 
        ];

        try {
            return Snap::getSnapToken($params);
        } catch (Exception $e) {
            throw new Exception("Gagal membuat transaksi Midtrans: " . $e->getMessage());
        }
    }
}