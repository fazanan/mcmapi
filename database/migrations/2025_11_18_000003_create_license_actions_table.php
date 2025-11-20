<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('license_actions', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 191)->nullable();
            $table->string('order_id', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('action', 50);
            $table->string('result', 20);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_actions');
    }
};