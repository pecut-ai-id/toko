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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            // Uang memakai decimal, bukan float (requirements.md Bagian 6).
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // SKU unik dalam cakupan bisnis.
            $table->unique(['business_id', 'sku']);
            $table->index(['business_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
