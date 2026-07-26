<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {

            $table->ulid('id')->primary();


            $table->foreignUlid('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();


            $table->foreignUlid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();


            $table->foreignUlid('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignUlid('uom_id')
                ->constrained('uoms')
                ->restrictOnDelete();

            $table->decimal(
                'qty',
                12,
                3
            );

            $table->decimal(
                'conversion_factor',
                12,
                3
            )
            ->default(1);

            $table->decimal(
                'base_qty',
                12,
                3
            );
            $table->decimal(
                'cost_price',
                15,
                2
            );
            $table->decimal(
                'selling_price',
                15,
                2
            );
            $table->decimal(
                'subtotal',
                15,
                2
            );
            $table->timestamps();

            $table->index([
                'company_id',
                'product_id',
                'created_at'
            ]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
