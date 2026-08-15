<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->index();
            $table->ulid('outlet_id')->nullable()->index();
            $table->ulid('transaction_id')->index();
            $table->ulid('account_id')->nullable()->index(); 
            $table->ulid('user_id')->nullable()->index(); 
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method');
            $table->text('notes')->nullable(); 

            // ==========================================
            // KEBUTUHAN INTEGRASI GATEWAY (MIDTRANS)
            // ==========================================
            $table->string('payment_status')->default('success'); 
            $table->string('payment_reference')->nullable()->index(); 
            $table->json('gateway_response')->nullable(); 

            $table->timestamps();
            
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};