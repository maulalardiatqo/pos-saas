<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUlid('brand_id')
                ->nullable()
                ->after('category_id')
                ->constrained('brands')
                ->nullOnDelete();

            $table->enum('product_type', ['standard', 'bundle', 'recipe'])
                ->default('standard')
                ->after('name');

            $table->boolean('has_variants')
                ->default(false)
                ->after('product_type');
            $table->index(['company_id', 'product_type']);
            
            $table->index(['company_id', 'has_variants']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'product_type']);
            $table->dropIndex(['company_id', 'has_variants']);
            
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['brand_id', 'product_type', 'has_variants']);
        });
    }
};