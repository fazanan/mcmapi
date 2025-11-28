<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('WhatsAppConfig', function (Blueprint $table) {
            $table->integer('Id');
            $table->primary('Id');
            $table->string('ApiSecret', 200)->nullable();
            $table->string('AccountUniqueId', 200)->nullable();
            $table->dateTime('UpdatedAt')->useCurrent();
            $table->dateTime('CreatedAt')->useCurrent();
        });

        DB::table('WhatsAppConfig')->insert([
            'Id' => 1,
            'ApiSecret' => '7df6fb62f2505eb3495f86b9a156ab3e1d81a2d6',
            'AccountUniqueId' => '17642896669b04d152845ec0a378394003c96da5946928ec8275c53',
            'UpdatedAt' => now('UTC'),
            'CreatedAt' => now('UTC'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('WhatsAppConfig');
    }
};