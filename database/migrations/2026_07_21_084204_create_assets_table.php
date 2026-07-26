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
        Schema::create('assets', function (Blueprint $table) {
            $table->ulid('id')->primary(); 
            
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('outlet_id')->nullable()->constrained()->nullOnDelete(); 
            
            $table->string('name');
            $table->string('asset_code'); 
            $table->string('category')->nullable(); 
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            
            $table->string('status')->default('active'); 
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
