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
            $table->string('seat_manager_type')->nullable()->after('total_seats');
            $table->foreignId('seat_manager_userware_id')
                ->nullable()
                ->after('seat_manager_type')
                ->constrained('userwares')
                ->nullOnDelete();
            $table->string('seat_manager_department')->nullable()->after('seat_manager_userware_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_manager_userware_id');
            $table->dropColumn(['seat_manager_type', 'seat_manager_department']);
        });
    }
};
