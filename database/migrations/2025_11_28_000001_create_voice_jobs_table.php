<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('text');
            $table->string('voice')->nullable();
            $table->float('speed')->default(1.0);
            $table->string('status')->default('queued');
            $table->string('path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_jobs');
    }
};