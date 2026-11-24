<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sync_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hardware_id')->nullable()->constrained('hardwares')->nullOnDelete();
            $table->foreignId('virtualware_id')->nullable()->constrained('virtualwares')->nullOnDelete();
            $table->string('identity_key');
            $table->string('device_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('platform', 32)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('message', 1000);
            $table->json('errors')->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->timestamps();

            $table->unique(['organization_id', 'identity_key']);
            $table->index(['organization_id', 'last_failed_at']);
            $table->index(['hardware_id', 'last_failed_at']);
            $table->index(['virtualware_id', 'last_failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_failures');
    }
};
