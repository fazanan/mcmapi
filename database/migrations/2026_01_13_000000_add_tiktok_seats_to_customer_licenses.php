<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->integer('max_seat_mass_upload_tiktok')->nullable()->default(0);
            $table->integer('used_seat_mass_upload_tiktok')->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->dropColumn(['max_seat_mass_upload_tiktok', 'used_seat_mass_upload_tiktok']);
        });
    }
};
