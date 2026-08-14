<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // 1. Tambahkan kolom untuk menyimpan nama item manual
            $table->string('item_name', 255)->nullable()->after('uom_id');
            
            // 2. Ubah product_id dan uom_id agar boleh kosong (NULL)
            $table->char('product_id', 26)->nullable()->change();
            $table->char('uom_id', 26)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
            $table->char('product_id', 26)->nullable(false)->change();
            $table->char('uom_id', 26)->nullable(false)->change();
        });
    }
};