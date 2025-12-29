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
            $table->integer('max_seats_shopee_scrap')->default(0)->after('max_seats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            $table->dropColumn('max_seats_shopee_scrap');
        });
    }
};
