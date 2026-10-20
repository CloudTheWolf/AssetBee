<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->foreignId('remote_package_id')->nullable()->constrained('remote_packages')->nullOnDelete();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('remote_push_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remote_push_id')->constrained('remote_pushes')->cascadeOnDelete();
            $table->foreignId('hardware_id')->constrained('hardwares')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('first_notified_at')->nullable();
            $table->timestamp('postpone_deadline_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->json('result_json')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['remote_push_id', 'hardware_id']);
            $table->index(['hardware_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_push_targets');
        Schema::dropIfExists('remote_pushes');
    }
};
