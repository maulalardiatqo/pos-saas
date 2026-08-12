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
            ['module' => 'inventory', 'code' => 'inventory.transfer', 'name' => 'Stock Transfer (Mutasi)'],
            ['module' => 'inventory', 'code' => 'inventory.history', 'name' => 'Lihat Riwayat & Saldo Stok'],

            // ---------------------------------------------------------
            // MODULE: FINANCE
            // ---------------------------------------------------------
            // Kasir Shift & Rekening adalah bagian dari Finance di sistem kita
            ['module' => 'finance', 'code' => 'finance.closing_shift', 'name' => 'Buka/Tutup Shift Kasir (Session)'],
            ['module' => 'finance', 'code' => 'finance.account', 'name' => 'Kelola Rekening / Akun Pembayaran'],

            // ---------------------------------------------------------
            // MODULE: PURCHASE
            // ---------------------------------------------------------
            // Sistem kita memproses masuknya barang langsung via status Completed di PO
            ['module' => 'purchase', 'code' => 'purchase.po', 'name' => 'Kelola Purchase Order (PO)'],

            // ---------------------------------------------------------
            // MODULE: CRM
            // ---------------------------------------------------------
            ['module' => 'crm', 'code' => 'crm.member', 'name' => 'Kelola Membership'],
            ['module' => 'crm', 'code' => 'crm.point', 'name' => 'Kelola Poin Pelanggan'],
            ['module' => 'crm', 'code' => 'crm.loyalty', 'name' => 'Kelola Program Loyalty & Reward'],
            ['module' => 'crm', 'code' => 'crm.voucher', 'name' => 'Kelola Voucher'],

            // ---------------------------------------------------------
            // MODULE: REPORTS
            // ---------------------------------------------------------
            ['module' => 'reports', 'code' => 'reports.sales', 'name' => 'Laporan Penjualan (Dashboard)'],
            ['module' => 'reports', 'code' => 'reports.product', 'name' => 'Laporan Analisis Produk'],

            // ---------------------------------------------------------
            // MODULE: SETTINGS
            // ---------------------------------------------------------
            ['module' => 'settings', 'code' => 'settings.company', 'name' => 'Ubah Profil Toko & Seting Midtrans'],
            ['module' => 'settings', 'code' => 'settings.roles', 'name' => 'Kelola Hak Akses & Jabatan'],
        ];

        // Kosongkan tabel permission lama sebelum membuat ulang agar rapi
        // (Hati-hati jika data RolePermission sudah terhubung, pastikan melakukan seeder ulang Role juga)
        
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