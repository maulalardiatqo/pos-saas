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
            $table->boolean('is_loyalty_enabled')->default(false)->after('name');
            $table->decimal('loyalty_spend_amount', 15, 2)->default(10000)->after('is_loyalty_enabled');
            $table->integer('loyalty_point_earned')->default(1)->after('loyalty_spend_amount'); 
            $table->decimal('loyalty_point_value', 15, 2)->default(100)->after('loyalty_point_earned'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'is_loyalty_enabled',
                'loyalty_spend_amount',
                'loyalty_point_earned',
                'loyalty_point_value'
            ]);
        });
    }
};
