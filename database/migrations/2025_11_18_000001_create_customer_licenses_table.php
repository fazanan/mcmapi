<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 191)->unique();
            $table->string('license_key', 191)->unique()->nullable();
            $table->string('owner', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('edition', 100)->nullable();
            $table->string('payment_status', 50)->nullable();
            $table->string('product_name', 191)->nullable();
            $table->integer('tenor_days')->nullable();
            $table->boolean('is_activated')->default(false);
            $table->timestamp('activation_date_utc')->nullable();
            $table->timestamp('expires_at_utc')->nullable();
            $table->string('machine_id', 191)->nullable();
            $table->integer('max_seats')->nullable();
            $table->integer('max_video')->nullable();
            $table->text('features')->nullable();
            $table->integer('vo_seconds_remaining')->default(0);
            $table->string('status', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_licenses');
    }
};