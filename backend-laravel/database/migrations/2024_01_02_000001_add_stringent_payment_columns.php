<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // For cash: what the cashier physically received (may exceed amount owed -> change).
            $table->decimal('tendered_amount', 12, 2)->nullable()->after('amount');
            // Client-generated key so a retried "charge" request (flaky network,
            // double-tap on the terminal) can never record two payments.
            $table->string('idempotency_key', 80)->nullable()->after('provider_reference');
            $table->unique(['tenant_id', 'order_id', 'idempotency_key'], 'payments_idempotency_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Optimistic-lock style guard rail in addition to row-level pessimistic
            // locks: lets us detect "this order was already cancelled/modified by
            // someone else between my read and my write" even outside a single
            // DB transaction (e.g. two API requests interleaved).
            $table->unsignedInteger('lock_version')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_idempotency_unique');
            $table->dropColumn(['tendered_amount', 'idempotency_key']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
