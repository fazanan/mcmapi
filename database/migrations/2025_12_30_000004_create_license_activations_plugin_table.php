<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations_plugin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->char('device_id', 36);
            $table->string('product_name', 100);
            $table->dateTime('activated_at')->useCurrent();
            $table->dateTime('last_seen_at')->useCurrent();
            $table->boolean('revoked')->default(0);

            $table->unique(['license_id', 'device_id', 'product_name'], 'uq_device_product');
            
            $table->foreign('license_id', 'fk_license_plugin')
                  ->references('id')
                  ->on('customer_licenses')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations_plugin');
    }
};
