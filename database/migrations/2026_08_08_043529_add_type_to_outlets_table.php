<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->enum('type', ['store', 'warehouse', 'canvas'])
                  ->default('store')
                  ->after('name') 
                  ->comment('Tipe outlet: Toko fisik, Gudang utama, atau Mobil Sales (Canvas)');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};