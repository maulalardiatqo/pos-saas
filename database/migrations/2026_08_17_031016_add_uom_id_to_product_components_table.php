<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_components', function (Blueprint $table) {
            $table->char('uom_id', 26)->nullable()->after('child_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_components', function (Blueprint $table) {
            $table->char('uom_id', 26)->nullable()->after('child_variant_id');
        });
    }
};
