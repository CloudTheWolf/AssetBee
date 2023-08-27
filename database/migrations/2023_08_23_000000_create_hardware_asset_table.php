<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hardware_asset', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('product_name');
            $table->integer('brand_name');
            $table->string('serial_number');
            $table->integer('vendor_id')->nullable();
            $table->string('custom_tracking_id')->nullable();
            $table->binary('photo')->nullable();
            $table->binary('purchase_order')->nullable();
            $table->integer('state')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hardware_asset');
    }
};
