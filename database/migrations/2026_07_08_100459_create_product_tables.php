<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUlid('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignUlid('base_uom_id')
                ->constrained('uoms')
                ->restrictOnDelete();

            $table->string('sku',100)->nullable();

            $table->string('barcode',100)->nullable();

            $table->string('name',200);

            $table->text('description')->nullable();

            $table->decimal('cost_price',15,2)->default(0);

            $table->decimal('base_price',15,2)->default(0);

            $table->string('image_url')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['company_id','sku']);

            $table->index(['company_id','barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};