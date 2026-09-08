<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('costable');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('seat_count')->nullable();
            $table->string('provider');
            $table->json('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['costable_type', 'costable_id', 'period_start', 'period_end'],
                'cost_snapshots_costable_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_snapshots');
    }
};
