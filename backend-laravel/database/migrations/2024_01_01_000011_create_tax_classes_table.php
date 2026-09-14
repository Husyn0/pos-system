<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('rate', 5, 2);
            $table->boolean('is_inclusive')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_classes');
    }
};
