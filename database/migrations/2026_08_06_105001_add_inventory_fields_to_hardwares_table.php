<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->timestamp('inventory_collected_at')->nullable()->after('is_vm_host');
            $table->longText('inventory_payload')->nullable()->after('inventory_collected_at');
            $table->index(['organization_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'serial_number']);
            $table->dropColumn(['inventory_collected_at', 'inventory_payload']);
        });
    }
};
