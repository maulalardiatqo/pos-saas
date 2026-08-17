<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Mengubah kolom menjadi boleh kosong (nullable)
            $table->char('base_uom_id', 26)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Mengembalikan ke semula jika di-rollback
            $table->char('base_uom_id', 26)->nullable(false)->change();
        });
    }
};