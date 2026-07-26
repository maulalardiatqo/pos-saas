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
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            $table->foreignUlid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
                
            $table->foreignUlid('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();
            $table->decimal('last_purchase_price', 15, 2)->default(0.00);
            
            $table->boolean('is_default_supplier')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};