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
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->string('version')->nullable();
            $table->timestamp('last_used')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->dropColumn(['version', 'last_used']);
        });
    }
};
