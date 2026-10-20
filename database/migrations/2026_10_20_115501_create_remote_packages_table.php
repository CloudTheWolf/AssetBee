<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source');
            $table->string('msi_url')->nullable();
            $table->string('msi_args')->nullable();
            $table->string('winget_id')->nullable();
            $table->string('choco_id')->nullable();
            $table->string('silent_args')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_packages');
    }
};
