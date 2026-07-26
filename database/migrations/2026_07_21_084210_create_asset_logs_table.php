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
        Schema::create('asset_logs', function (Blueprint $table) {
            $table->ulid('id')->primary(); 
            
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete(); 
            
            $table->string('action_type'); 
            
            $table->foreignUlid('from_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignUlid('to_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            
            $table->string('remarks')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_logs');
    }
};
