<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {

            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 50);

            $table->string('name', 100);
            $table->boolean('is_system')
                ->default(false);

            $table->timestamps();

            $table->softDeletes();


            $table->unique([
                'company_id',
                'code'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};