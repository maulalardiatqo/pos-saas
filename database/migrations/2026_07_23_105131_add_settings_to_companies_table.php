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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('name');
            $table->string('nota_size', 20)->default('58mm')->after('logo');
            $table->boolean('is_nota_logo')->default(true)->after('nota_size');;
            $table->boolean('pos_with_img')->default(true)->after('is_nota_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo', 'nota_size', 'pos_with_img']);
        });
    }
};