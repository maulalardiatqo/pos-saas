<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {

            $table->ulid('id')->primary();


            $table->foreignUlid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();


            /*
             * Kode outlet
             * Contoh:
             * MAIN
             * JKT01
             * BDG01
             */
            $table->string('code',50);


            $table->string('name',150);


            $table->text('address')
                ->nullable();


            $table->string('phone',20)
                ->nullable();


            $table->boolean('is_active')
                ->default(true);


            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'company_id',
                'code'
            ]);


            $table->index([
                'company_id',
                'is_active'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};