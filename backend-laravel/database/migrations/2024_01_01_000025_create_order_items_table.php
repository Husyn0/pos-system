<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUuid('item_variant_id')->nullable()->constrained('item_variants')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->string('kitchen_station', 50)->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('notes')->nullable();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->constrained('modifiers');
            $table->decimal('price_delta', 10, 2)->default(0);
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('discount_id')->constrained('discounts');
            $table->decimal('amount_applied', 10, 2);
            $table->primary(['order_id', 'discount_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
    }
};
