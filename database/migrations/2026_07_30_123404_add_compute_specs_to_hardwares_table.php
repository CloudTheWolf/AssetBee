<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->string('operating_system')->nullable()->after('model');
            $table->string('cpu')->nullable()->after('operating_system');
            $table->unsignedInteger('ram_gb')->nullable()->after('cpu');
            $table->unsignedInteger('storage_gb')->nullable()->after('ram_gb');
            $table->string('bitlocker_status')->nullable()->after('storage_gb');
            $table->text('bitlocker_recovery_key')->nullable()->after('bitlocker_status');
            $table->boolean('is_vm_host')->default(false)->after('bitlocker_recovery_key');
        });
    }

    public function down(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->dropColumn([
                'operating_system',
                'cpu',
                'ram_gb',
                'storage_gb',
                'bitlocker_status',
                'bitlocker_recovery_key',
                'is_vm_host',
            ]);
        });
    }
};
