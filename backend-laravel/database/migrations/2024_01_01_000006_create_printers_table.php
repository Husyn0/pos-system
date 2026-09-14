<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('name', 100);
            $table->string('role', 20); // receipt | kitchen | bar | label
            $table->string('connection_type', 20); // network | usb | bluetooth
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->nullable();
            $table->string('usb_identifier', 100)->nullable();
            $table->integer('paper_width_mm')->default(80);
            $table->boolean('has_cash_drawer')->default(false);
            $table->string('kitchen_station', 50)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
