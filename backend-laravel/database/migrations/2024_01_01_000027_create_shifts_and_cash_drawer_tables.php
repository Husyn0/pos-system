<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamp('clock_in')->useCurrent();
            $table->timestamp('clock_out')->nullable();
            $table->string('status', 20)->default('open');
        });

        Schema::create('cash_drawer_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->decimal('opening_amount', 10, 2)->default(0);
            $table->decimal('closing_amount', 10, 2)->nullable();
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('cash_drawer_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cash_drawer_session_id')->constrained('cash_drawer_sessions')->cascadeOnDelete();
            $table->string('type', 20); // sale | payout | payin | refund | opening
            $table->decimal('amount', 10, 2);
            $table->foreignUuid('reference_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_transactions');
        Schema::dropIfExists('cash_drawer_sessions');
        Schema::dropIfExists('shifts');
    }
};
