<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->text('proxmox_credentials')->nullable()->after('is_vm_host');
            $table->timestamp('proxmox_credentials_verified_at')->nullable()->after('proxmox_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('hardwares', function (Blueprint $table) {
            $table->dropColumn([
                'proxmox_credentials',
                'proxmox_credentials_verified_at',
            ]);
        });
    }
};
