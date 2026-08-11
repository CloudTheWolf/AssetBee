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
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('GBP');
            $table->string('billing_interval')->default('monthly');
            $table->string('stripe_price_id')->unique();
            $table->unsignedInteger('sort_order')->default(0);
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
        Schema::dropIfExists('subscription_packages');
    }
};
