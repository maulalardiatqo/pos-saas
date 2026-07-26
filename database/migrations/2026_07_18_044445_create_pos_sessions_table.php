<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Membuat tabel Sesi Kasir (Shift)
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('opening_time');
            $table->dateTime('closing_time')->nullable();
            
            $table->enum('status', ['open', 'closed'])->default('open');

            // PENCATATAN UANG (CASH MANAGEMENT)
            $table->decimal('opening_amount', 15, 2)->default(0); // Modal Awal / Kembalian Pagi
            $table->decimal('expected_closing_amount', 15, 2)->nullable(); // Uang seharusnya menurut sistem
            $table->decimal('actual_closing_amount', 15, 2)->nullable(); // Uang fisik aktual di laci
            $table->decimal('difference', 15, 2)->nullable(); // Selisih (Plus/Minus)

            // RINGKASAN OMSET SHIFT
            $table->decimal('total_sales', 15, 2)->default(0); // Total semua metode pembayaran
            $table->decimal('total_cash_sales', 15, 2)->default(0); // Khusus omset Tunai (yang masuk ke laci)

            $table->text('notes')->nullable(); // Catatan kasir saat tutup shift

            $table->timestamps();
            $table->softDeletes();

            // Indeks untuk mempercepat pencarian data
            $table->index(['company_id', 'outlet_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // 2. Menambahkan relasi pos_session_id ke tabel transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUlid('pos_session_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('pos_sessions')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['pos_session_id']);
            $table->dropColumn('pos_session_id');
        });
        
        Schema::dropIfExists('pos_sessions');
    }
};