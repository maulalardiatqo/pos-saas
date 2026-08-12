<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ---------------------------------------------------------
            // MODULE: USERS, ROLES, OUTLETS (CORE)
            // ---------------------------------------------------------
            ['module' => 'users', 'code' => 'users.view', 'name' => 'Lihat Karyawan'],
            ['module' => 'users', 'code' => 'users.create', 'name' => 'Tambah Karyawan'],
            ['module' => 'users', 'code' => 'users.edit', 'name' => 'Edit Karyawan'],
            ['module' => 'users', 'code' => 'users.delete', 'name' => 'Hapus Karyawan'],

            ['module' => 'roles', 'code' => 'roles.view', 'name' => 'Lihat Jabatan'],
            ['module' => 'roles', 'code' => 'roles.create', 'name' => 'Tambah Jabatan'],
            ['module' => 'roles', 'code' => 'roles.edit', 'name' => 'Edit Jabatan'],
            ['module' => 'roles', 'code' => 'roles.delete', 'name' => 'Hapus Jabatan'],

            ['module' => 'outlets', 'code' => 'outlets.view', 'name' => 'Lihat Cabang'],
            ['module' => 'outlets', 'code' => 'outlets.create', 'name' => 'Tambah Cabang'],
            ['module' => 'outlets', 'code' => 'outlets.edit', 'name' => 'Edit Cabang'],
            ['module' => 'outlets', 'code' => 'outlets.delete', 'name' => 'Hapus Cabang'],

            // ---------------------------------------------------------
            // MODULE: SALES & POS
            // ---------------------------------------------------------
            ['module' => 'sales', 'code' => 'sales.view', 'name' => 'Lihat Transaksi Penjualan'],
            ['module' => 'sales', 'code' => 'sales.create', 'name' => 'Buat Transaksi (POS)'],
            ['module' => 'sales', 'code' => 'sales.edit', 'name' => 'Edit Transaksi'],
            ['module' => 'sales', 'code' => 'sales.delete', 'name' => 'Hapus/Void Transaksi'],

            // ---------------------------------------------------------
            // MODULE: PRODUCTS & CATALOG
            // ---------------------------------------------------------
            ['module' => 'products', 'code' => 'products.view', 'name' => 'Lihat Produk'],
            ['module' => 'products', 'code' => 'products.create', 'name' => 'Tambah Produk'],
            ['module' => 'products', 'code' => 'products.edit', 'name' => 'Edit Produk'],
            ['module' => 'products', 'code' => 'products.delete', 'name' => 'Hapus Produk'],
            ['module' => 'products', 'code' => 'products.category', 'name' => 'Kelola Kategori'],
            ['module' => 'products', 'code' => 'products.brand', 'name' => 'Kelola Brand'],
            // ['module' => 'products', 'code' => 'products.variant', 'name' => 'Kelola Varian'],
            ['module' => 'products', 'code' => 'products.bundle', 'name' => 'Kelola Produk Bundle'],
            ['module' => 'products', 'code' => 'products.recipe', 'name' => 'Kelola Resep (BOM)'],
            ['module' => 'products', 'code' => 'products.barcode', 'name' => 'Kelola Barcode'],
            ['module' => 'products', 'code' => 'products.multi_uom', 'name' => 'Kelola Multi Satuan (UOM)'],

            // ---------------------------------------------------------
            // MODULE: CUSTOMERS & SUPPLIERS
            // ---------------------------------------------------------
            ['module' => 'customers', 'code' => 'customers.view', 'name' => 'Lihat Pelanggan'],
            ['module' => 'customers', 'code' => 'customers.create', 'name' => 'Tambah Pelanggan'],
            ['module' => 'customers', 'code' => 'customers.edit', 'name' => 'Edit Pelanggan'],
            ['module' => 'customers', 'code' => 'customers.delete', 'name' => 'Hapus Pelanggan'],

            ['module' => 'suppliers', 'code' => 'suppliers.view', 'name' => 'Lihat Supplier'],
            ['module' => 'suppliers', 'code' => 'suppliers.manage', 'name' => 'Kelola Supplier (Tambah/Edit/Hapus)'],

            // ---------------------------------------------------------
            // MODULE: INVENTORY
            // ---------------------------------------------------------
            ['module' => 'inventory', 'code' => 'inventory.adjustment', 'name' => 'Stock Adjustment'],
            ['module' => 'inventory', 'code' => 'inventory.transfer', 'name' => 'Stock Transfer'],
            ['module' => 'inventory', 'code' => 'inventory.opname', 'name' => 'Stock Opname'],
            ['module' => 'inventory', 'code' => 'inventory.history', 'name' => 'Lihat Riwayat Stok'],
            // ['module' => 'inventory', 'code' => 'inventory.stock_card', 'name' => 'Lihat Kartu Stok'],
            // ['module' => 'inventory', 'code' => 'inventory.warehouse', 'name' => 'Kelola Multi Gudang'],
            // ['module' => 'inventory', 'code' => 'inventory.batch', 'name' => 'Kelola Batch Number'],
            // ['module' => 'inventory', 'code' => 'inventory.expiry', 'name' => 'Kelola Expiry Date'],
            // ['module' => 'inventory', 'code' => 'inventory.serial', 'name' => 'Kelola Serial Number'],
            // ['module' => 'inventory', 'code' => 'inventory.reorder', 'name' => 'Kelola Reorder Level'],

            // ---------------------------------------------------------
            // MODULE: FINANCE
            // ---------------------------------------------------------
            // ['module' => 'finance', 'code' => 'finance.cash_in', 'name' => 'Catat Kas Masuk (Cash In)'],
            // ['module' => 'finance', 'code' => 'finance.cash_out', 'name' => 'Catat Kas Keluar (Cash Out)'],
            ['module' => 'finance', 'code' => 'finance.expense', 'name' => 'Catat Biaya/Pengeluaran'],
            ['module' => 'finance', 'code' => 'finance.revenue', 'name' => 'Catat Pendapatan Lain'],
            // ['module' => 'finance', 'code' => 'finance.closing_shift', 'name' => 'Tutup Shift'],
            // ['module' => 'finance', 'code' => 'finance.closing_day', 'name' => 'Tutup Hari'],
            // ['module' => 'finance', 'code' => 'finance.bank', 'name' => 'Kelola Akun Bank'],
            // ['module' => 'finance', 'code' => 'finance.payment_method', 'name' => 'Kelola Metode Pembayaran'],
            // ['module' => 'finance', 'code' => 'finance.tax', 'name' => 'Kelola Pajak'],
            // ['module' => 'finance', 'code' => 'finance.journal', 'name' => 'Lihat Jurnal Akuntansi'],

            // ---------------------------------------------------------
            // MODULE: PURCHASE
            // ---------------------------------------------------------
            ['module' => 'purchase', 'code' => 'purchase.po', 'name' => 'Buat Purchase Order (PO)'],
            ['module' => 'purchase', 'code' => 'purchase.goods_receive', 'name' => 'Terima Barang (Goods Receive)'],
            ['module' => 'purchase', 'code' => 'purchase.return_supplier', 'name' => 'Retur ke Supplier'],
            ['module' => 'purchase', 'code' => 'purchase.request', 'name' => 'Buat Purchase Request'],
            ['module' => 'purchase', 'code' => 'purchase.invoice', 'name' => 'Kelola Supplier Invoice'],

            // ---------------------------------------------------------
            // MODULE: CRM
            // ---------------------------------------------------------
            ['module' => 'crm', 'code' => 'crm.member', 'name' => 'Kelola Membership'],
            ['module' => 'crm', 'code' => 'crm.point', 'name' => 'Kelola Poin Pelanggan'],
            ['module' => 'crm', 'code' => 'crm.loyalty', 'name' => 'Kelola Program Loyalty'],
            ['module' => 'crm', 'code' => 'crm.voucher', 'name' => 'Kelola Voucher'],
            ['module' => 'crm', 'code' => 'crm.gift_card', 'name' => 'Kelola Gift Card'],
            ['module' => 'crm', 'code' => 'crm.coupon', 'name' => 'Kelola Kupon Diskon'],

            // ---------------------------------------------------------
            // MODULE: PROMOTIONS & KITCHEN (NEW)
            // ---------------------------------------------------------
            ['module' => 'promotions', 'code' => 'promotions.manage', 'name' => 'Kelola Promo & Diskon'],
            ['module' => 'kitchen', 'code' => 'kitchen.view', 'name' => 'Lihat Kitchen Display (KDS)'],
            
            // ---------------------------------------------------------
            // MODULE: REPORTS
            // ---------------------------------------------------------
            ['module' => 'reports', 'code' => 'reports.sales', 'name' => 'Laporan Penjualan'],
            // ['module' => 'reports', 'code' => 'reports.inventory', 'name' => 'Laporan Inventori'],
            ['module' => 'reports', 'code' => 'reports.finance', 'name' => 'Laporan Keuangan'],
            // ['module' => 'reports', 'code' => 'reports.purchase', 'name' => 'Laporan Pembelian'],
            // ['module' => 'reports', 'code' => 'reports.customer', 'name' => 'Laporan Pelanggan'],
            ['module' => 'reports', 'code' => 'reports.product', 'name' => 'Laporan Produk'],

            // ---------------------------------------------------------
            // MODULE: ADVANCED & INTEGRATIONS
            // ---------------------------------------------------------
            ['module' => 'advanced', 'code' => 'advanced.audit_log', 'name' => 'Lihat Audit Log'],
            ['module' => 'advanced', 'code' => 'advanced.approval', 'name' => 'Kelola Approval Workflow'],
            ['module' => 'integrations', 'code' => 'integrations.manage', 'name' => 'Kelola Integrasi (API, WA, dll)'],

            // ---------------------------------------------------------
            // MODULE: SETTINGS
            // ---------------------------------------------------------
            ['module' => 'settings', 'code' => 'settings.company', 'name' => 'Ubah Profil Toko'],
            ['module' => 'settings', 'code' => 'settings.roles', 'name' => 'Kelola Hak Akses & Jabatan'],
            ['module' => 'settings', 'code' => 'settings.devices', 'name' => 'Kelola Perangkat/Printer'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['code' => $perm['code']], 
                [
                    'id' => Str::ulid(),
                    'name' => $perm['name'],
                    'module' => $perm['module'],
                ]
            );
        }
    }
}