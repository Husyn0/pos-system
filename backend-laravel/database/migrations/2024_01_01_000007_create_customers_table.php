<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('password_hash')->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->string('loyalty_tier', 30)->default('bronze');
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
