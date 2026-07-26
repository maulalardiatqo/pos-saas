<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {

            $table->ulid('id')->primary();

            /*
             * Contoh:
             * sales.create
             * sales.update
             * product.delete
             */
            $table->string('code',100)
                ->unique();


            /*
             * Nama tampilan
             */
            $table->string('name',150);


            /*
             * Pengelompokan menu
             *
             * sales
             * inventory
             * finance
             */
            $table->string('module',50);


            $table->timestamps();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};