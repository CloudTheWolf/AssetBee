<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->string('cost_sync_provider')->default('none')->after('notes');
            $table->text('cost_sync_credentials')->nullable()->after('cost_sync_provider');
            $table->text('cost_sync_request')->nullable()->after('cost_sync_credentials');
            $table->timestamp('cost_synced_at')->nullable()->after('cost_sync_request');
            $table->text('cost_sync_error')->nullable()->after('cost_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->dropColumn([
                'cost_sync_provider',
                'cost_sync_credentials',
                'cost_sync_request',
                'cost_synced_at',
                'cost_sync_error',
            ]);
        });
    }
};
