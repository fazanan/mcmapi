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
            $table->string('license_key'); // Menggunakan license_key langsung
            $table->char('device_id', 36);
            $table->string('product_name', 100);
            $table->dateTime('activated_at')->useCurrent();
            $table->dateTime('last_seen_at')->useCurrent();
            $table->boolean('revoked')->default(0);

            // Unique berdasarkan license_key, device_id, product_name
            $table->unique(['license_key', 'device_id', 'product_name'], 'uq_device_product');
            
            // Index untuk pencarian cepat
            $table->index('license_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations_plugin');
    }
};
