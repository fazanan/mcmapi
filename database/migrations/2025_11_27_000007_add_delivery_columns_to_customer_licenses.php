<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->string('delivery_status', 20)->nullable();
            $table->text('delivery_log')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->dropColumn(['delivery_status','delivery_log']);
        });
    }
};