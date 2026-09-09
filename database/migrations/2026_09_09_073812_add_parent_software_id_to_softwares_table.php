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
        Schema::table('softwares', function (Blueprint $table) {
            $table->foreignId('parent_software_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('softwares')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_software_id');
        });
    }
};
