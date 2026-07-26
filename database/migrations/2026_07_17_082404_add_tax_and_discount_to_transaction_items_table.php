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
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('discount_rate', 5, 2)->default(0)->after('selling_price')->comment('Persentase diskon item, misal: 10.00');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate')->comment('Nominal uang diskon item');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount')->comment('Persentase pajak item, misal: 11.00');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate')->comment('Nominal uang pajak item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'discount_rate',
                'discount_amount',
                'tax_rate',
                'tax_amount'
            ]);
        });
    }
};