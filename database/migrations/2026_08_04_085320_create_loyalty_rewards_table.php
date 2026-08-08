<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            
            $table->string('name');
            $table->integer('points_required');
            $table->enum('reward_type', ['product', 'discount'])->default('product');
            
            $table->foreignUlid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->decimal('discount_amount', 15, 2)->nullable();
            
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};