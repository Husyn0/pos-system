<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->foreignId('tax_class_id')->nullable()->constrained('tax_classes')->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('sku', 60)->nullable();
            $table->decimal('base_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->integer('calories')->nullable();
            $table->integer('prep_time_minutes')->default(5);
            $table->boolean('track_inventory')->default(false);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
