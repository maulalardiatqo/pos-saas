<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {

            $table->ulid('id')->primary();
            $table->string('name',150);

            $table->string('email')
                ->unique();

            $table->string('phone',30)
                ->nullable();


            $table->text('address')
                ->nullable();


            $table->foreignUlid('subscription_plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            $table->enum('status',[
                'active',
                'suspended',
                'expired'
            ])
            ->default('active');


            $table->date('valid_until')
                ->nullable();


            $table->timestamps();

            $table->softDeletes();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};