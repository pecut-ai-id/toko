<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit: siapa melakukan apa, atas dokumen yang mana, kapan.
     *
     * Barisnya **tidak pernah diubah atau dihapus** (tidak ada
     * `updated_at`), dan hanya menyimpan ringkasan perubahan yang penting
     * --- bukan salinan seluruh baris tabel lain.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Pelaku bisa saja dinonaktifkan/dihapus; jejaknya dibiarkan
            // tanpa nama daripada memaksa barisnya ikut terhapus.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            // Subjek bisa berupa dokumen transaksi atau data master.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'action']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
