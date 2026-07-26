<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            
            $table->string('card_number', 50); 
            $table->decimal('balance', 15, 2)->default(0); 
            
            $table->dateTime('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'card_number', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};