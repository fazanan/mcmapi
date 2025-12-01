<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('WhatsAppConfig', function (Blueprint $table) {
            if (!Schema::hasColumn('WhatsAppConfig','GroupLink')) {
                $table->string('GroupLink', 500)->nullable();
            }
            if (!Schema::hasColumn('WhatsAppConfig','InstallerLink')) {
                $table->string('InstallerLink', 500)->nullable();
            }
            if (!Schema::hasColumn('WhatsAppConfig','InstallerVersion')) {
                $table->string('InstallerVersion', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('WhatsAppConfig', function (Blueprint $table) {
            if (Schema::hasColumn('WhatsAppConfig','GroupLink')) {
                $table->dropColumn('GroupLink');
            }
            if (Schema::hasColumn('WhatsAppConfig','InstallerLink')) {
                $table->dropColumn('InstallerLink');
            }
            if (Schema::hasColumn('WhatsAppConfig','InstallerVersion')) {
                $table->dropColumn('InstallerVersion');
            }
        });
    }
};

