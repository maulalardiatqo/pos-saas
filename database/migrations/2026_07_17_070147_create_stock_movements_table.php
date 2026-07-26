<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('outlet_id')->nullable()->constrained('outlets')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 50); 
            
            $table->ulidMorphs('reference'); 
            
            $table->decimal('quantity', 10, 2); 
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            $table->index(['company_id', 'outlet_id', 'product_id', 'type']);
        });
    }

    public function down(): void { Schema::dropIfExists('stock_movements'); }
};