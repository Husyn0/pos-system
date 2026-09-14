<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('label', 20);
            $table->integer('capacity')->default(2);
            $table->string('status', 20)->default('available');
            $table->string('qr_token', 64)->nullable()->unique();
            $table->unique(['branch_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
