<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->json('secondary_ips')->nullable()->after('vpc_id');
        });
    }

    public function down(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->dropColumn('secondary_ips');
        });
    }
};
