<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            
            $table->foreignUlid('outlet_id')->nullable()->constrained('outlets')->cascadeOnDelete();
            
            $table->string('code')->unique(); 
            $table->string('name'); 
            $table->string('account_number')->nullable();
            
            $table->json('payment_methods'); 
            
            $table->decimal('balance', 15, 2)->default(0); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};