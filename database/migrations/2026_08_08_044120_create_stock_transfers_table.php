<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Induk (Dokumen Surat Jalan / Mutasi)
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            
            $table->string('reference_number')->unique();
            $table->foreignUlid('from_outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->foreignUlid('to_outlet_id')->constrained('outlets')->restrictOnDelete();
            
            $table->date('transfer_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
        });
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};