<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_google_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->timestamps();

            $table->unique('domain');
            $table->unique(['organization_id', 'domain']);
        });

        if (Schema::hasColumn('organizations', 'google_hosted_domain')) {
            $organizations = DB::table('organizations')
                ->whereNotNull('google_hosted_domain')
                ->get(['id', 'google_hosted_domain', 'created_at', 'updated_at']);

            foreach ($organizations as $organization) {
                DB::table('organization_google_domains')->insert([
                    'organization_id' => $organization->id,
                    'domain' => strtolower((string) $organization->google_hosted_domain),
                    'created_at' => $organization->created_at,
                    'updated_at' => $organization->updated_at,
                ]);
            }

            Schema::table('organizations', function (Blueprint $table) {
                $table->dropUnique(['google_hosted_domain']);
                $table->dropColumn('google_hosted_domain');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('organizations', 'google_hosted_domain')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('google_hosted_domain')->nullable()->unique();
            });

            $domains = DB::table('organization_google_domains')
                ->orderBy('id')
                ->get(['organization_id', 'domain']);

            $seen = [];

            foreach ($domains as $domain) {
                if (isset($seen[$domain->organization_id])) {
                    continue;
                }

                DB::table('organizations')
                    ->where('id', $domain->organization_id)
                    ->update(['google_hosted_domain' => $domain->domain]);

                $seen[$domain->organization_id] = true;
            }
        }

        Schema::dropIfExists('organization_google_domains');
    }
};
