<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Gunakan nullable agar bisa menjadi pelanggan "Global"
            $table->foreignUlid('outlet_id')->nullable()->constrained('outlets')->nullOnDelete()->after('company_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignUlid('outlet_id')->nullable()->constrained('outlets')->nullOnDelete()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });
    }
};