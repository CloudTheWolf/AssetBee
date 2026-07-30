<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->foreignId('cloud_tenant_id')
                ->nullable()
                ->after('host_hardware_id')
                ->constrained('cloud_tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cloud_tenant_id');
        });
    }
};
