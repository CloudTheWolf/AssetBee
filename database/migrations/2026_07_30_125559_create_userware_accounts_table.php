<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('userware_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('userware_id')->constrained('userwares')->cascadeOnDelete();
            $table->foreignId('software_id')->nullable()->constrained('softwares')->nullOnDelete();
            $table->string('site_name')->nullable();
            $table->string('site_url')->nullable();
            $table->string('username')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'userware_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userware_accounts');
    }
};
