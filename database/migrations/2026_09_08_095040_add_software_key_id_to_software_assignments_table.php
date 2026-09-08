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
        Schema::table('software_assignments', function (Blueprint $table) {
            $table->foreignId('software_key_id')
                ->nullable()
                ->after('userware_id')
                ->constrained('software_keys')
                ->nullOnDelete();

            $table->unique('software_key_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software_assignments', function (Blueprint $table) {
            $table->dropUnique(['software_key_id']);
            $table->dropConstrainedForeignId('software_key_id');
        });
    }
};
