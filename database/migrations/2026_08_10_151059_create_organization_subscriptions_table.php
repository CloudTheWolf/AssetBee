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
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_name');
            $table->string('status')->default('active')->index();
            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('GBP');
            $table->string('billing_interval')->default('monthly');
            $table->date('renews_at')->nullable();
            $table->unsignedInteger('member_limit')->nullable();
            $table->unsignedInteger('userware_limit')->nullable();
            $table->unsignedInteger('hardware_limit')->nullable();
            $table->unsignedInteger('virtualware_limit')->nullable();
            $table->unsignedInteger('software_limit')->nullable();
            $table->unsignedInteger('cloud_tenant_limit')->nullable();
            $table->unsignedInteger('asset_document_limit')->nullable();
            $table->unsignedInteger('userware_account_limit')->nullable();
            $table->unsignedInteger('api_key_limit')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
