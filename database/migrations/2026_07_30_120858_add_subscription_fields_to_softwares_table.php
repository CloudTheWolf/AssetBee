<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('expires_at');
            $table->string('billing_interval')->nullable()->after('is_recurring');
            $table->decimal('billing_amount', 12, 2)->nullable()->after('billing_interval');
            $table->string('currency', 3)->default('GBP')->after('billing_amount');
            $table->date('next_billing_at')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('softwares', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'billing_interval',
                'billing_amount',
                'currency',
                'next_billing_at',
            ]);
        });
    }
};
