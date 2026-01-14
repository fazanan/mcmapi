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
            if (!Schema::hasColumn('customer_licenses', 'massvoseat')) {
                $table->integer('massvoseat')->nullable()->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('customer_licenses', 'massvoseat')) {
                $table->dropColumn('massvoseat');
            }
        });
    }
};
