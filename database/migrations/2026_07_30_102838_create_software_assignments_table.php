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
        Schema::create('software_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('softwares')->cascadeOnDelete();
            $table->foreignId('userware_id')->constrained('userwares')->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['software_id', 'userware_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software_assignments');
    }
};
