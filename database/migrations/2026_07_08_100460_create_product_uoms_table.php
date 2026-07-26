<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_uoms', function (Blueprint $table) {

            $table->ulid('id')->primary();

            $table->foreignUlid('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignUlid('uom_id')
                ->constrained('uoms')
                ->restrictOnDelete();

            $table->decimal('conversion_factor',15,4)
                ->default(1);

            $table->decimal('selling_price',15,2)
                ->default(0);
            $table->string('barcode',100)
                ->nullable();
            $table->boolean('is_default')
                ->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique([
                'product_id',
                'uom_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
    }
};