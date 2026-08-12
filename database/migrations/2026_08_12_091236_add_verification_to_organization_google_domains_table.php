<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_google_domains', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable()->after('domain');
            $table->timestamp('verified_at')->nullable()->after('verification_token');
            $table->timestamp('verification_last_checked_at')->nullable()->after('verified_at');
        });

        DB::table('organization_google_domains')
            ->whereNull('verification_token')
            ->orderBy('id')
            ->chunkById(100, function ($domains): void {
                foreach ($domains as $domain) {
                    DB::table('organization_google_domains')
                        ->where('id', $domain->id)
                        ->update([
                            'verification_token' => Str::lower(Str::random(40)),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_google_domains', function (Blueprint $table) {
            $table->dropColumn([
                'verification_token',
                'verified_at',
                'verification_last_checked_at',
            ]);
        });
    }
};
