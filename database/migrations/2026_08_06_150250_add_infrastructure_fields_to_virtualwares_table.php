<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->string('region')->nullable()->after('notes');
            $table->string('instance_type')->nullable()->after('region');
            $table->string('private_ip')->nullable()->after('instance_type');
            $table->string('public_ip')->nullable()->after('private_ip');
            $table->string('availability_zone')->nullable()->after('public_ip');
            $table->string('subnet_id')->nullable()->after('availability_zone');
            $table->string('vpc_id')->nullable()->after('subnet_id');
            $table->json('disks')->nullable()->after('vpc_id');
            $table->boolean('termination_protection')->nullable()->after('disks');
        });
    }

    public function down(): void
    {
        Schema::table('virtualwares', function (Blueprint $table) {
            $table->dropColumn([
                'region',
                'instance_type',
                'private_ip',
                'public_ip',
                'availability_zone',
                'subnet_id',
                'vpc_id',
                'disks',
                'termination_protection',
            ]);
        });
    }
};
