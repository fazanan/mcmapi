<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_over_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('customer_licenses')->onDelete('cascade');
            $table->string('type', 20);
            $table->integer('seconds');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_over_transactions');
    }
};