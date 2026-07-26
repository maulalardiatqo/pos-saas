<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {

            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('symbol', 20)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};