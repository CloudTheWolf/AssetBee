<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_audits', function (Blueprint $table) {
            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('summary')->nullable()->after('target_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_audits', function (Blueprint $table) {
            $table->dropColumn(['actor_name', 'summary']);
        });
    }
};
