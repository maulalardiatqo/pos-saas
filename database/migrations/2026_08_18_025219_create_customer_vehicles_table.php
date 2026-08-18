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
        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->enum('jenis', ['matic', 'bebek', 'sport', 'adventure', 'motor elektronik']);
            $table->string('type', 255); 
            $table->string('warna', 255); 
            $table->string('nomor_plat', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
    }
};
