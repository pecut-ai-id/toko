<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penjualan langsung memengaruhi stok, jadi tidak ada fase draf:
     * statusnya `completed` sejak dibuat, atau `cancelled` bila dibatalkan.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Master data yang sudah dipakai transaksi tidak boleh terhapus.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->date('sold_at');
            $table->string('status', 20)->default('completed');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'sold_at']);
            $table->index(['business_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
