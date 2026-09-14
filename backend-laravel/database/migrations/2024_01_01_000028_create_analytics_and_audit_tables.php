<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('branch_id')->nullable();
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['tenant_id', 'event_type', 'occurred_at']);
        });

        Schema::create('daily_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->date('summary_date');
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->integer('orders_count')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->uuid('top_item_id')->nullable();
            $table->unique(['branch_id', 'summary_date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('title', 150)->nullable();
            $table->string('body', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 60);
            $table->string('auditable_type', 60);
            $table->uuid('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('daily_sales_summary');
        Schema::dropIfExists('analytics_events');
    }
};
