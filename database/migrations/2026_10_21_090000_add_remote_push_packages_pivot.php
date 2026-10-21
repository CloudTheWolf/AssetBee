<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_push_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remote_push_id')->constrained('remote_pushes')->cascadeOnDelete();
            $table->foreignId('remote_package_id')->constrained('remote_packages')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['remote_push_id', 'remote_package_id']);
        });

        $pushes = DB::table('remote_pushes')
            ->whereNotNull('remote_package_id')
            ->orderBy('id')
            ->get(['id', 'remote_package_id', 'created_at', 'updated_at']);

        foreach ($pushes as $push) {
            DB::table('remote_push_package')->insert([
                'remote_push_id' => $push->id,
                'remote_package_id' => $push->remote_package_id,
                'position' => 0,
                'created_at' => $push->created_at,
                'updated_at' => $push->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_push_package');
    }
};
