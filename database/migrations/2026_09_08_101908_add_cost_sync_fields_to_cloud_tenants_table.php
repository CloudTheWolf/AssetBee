<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_tenants', function (Blueprint $table) {
            $table->string('cost_sync_provider')->default('none')->after('credentials_verified_at');
            $table->text('cost_sync_request')->nullable()->after('cost_sync_provider');
            $table->decimal('billing_amount', 12, 2)->nullable()->after('cost_sync_request');
            $table->string('currency', 3)->default('USD')->after('billing_amount');
            $table->string('billing_interval')->nullable()->after('currency');
            $table->timestamp('cost_synced_at')->nullable()->after('billing_interval');
            $table->text('cost_sync_error')->nullable()->after('cost_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cost_sync_provider',
                'cost_sync_request',
                'billing_amount',
                'currency',
                'billing_interval',
                'cost_synced_at',
                'cost_sync_error',
            ]);
        });
    }
};
