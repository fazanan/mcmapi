<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ConfigApiKey', function (Blueprint $table) {
            $table->integer('ApiKeyId');
            $table->primary('ApiKeyId');
            $table->string('JenisApiKey', 50)->nullable();
            $table->string('Project', 200)->nullable();
            $table->string('ApiKey', 500)->nullable();
            $table->string('DefaultVoiceId', 50)->nullable();
            $table->string('Model', 100)->default('gemini-2.0-flash');
            $table->integer('RpmLimit')->default(60);
            $table->integer('RpdLimit')->default(5000);
            $table->integer('MinuteCount')->default(0);
            $table->integer('DayCount')->default(0);
            $table->dateTime('MinuteWindowEndPT')->useCurrent();
            $table->dateTime('DayWindowEndPT')->useCurrent();
            $table->string('Status', 20)->default('AVAILABLE');
            $table->dateTime('CooldownUntilPT')->nullable();
            $table->dateTime('UpdatedAt')->useCurrent();
            $table->dateTime('CreatedAt')->useCurrent();
        });

        $k1 = env('CONFIG_APIKEY_1');
        $k2 = env('CONFIG_APIKEY_2');
        $k3 = env('CONFIG_APIKEY_3');

        $rows = [];
        if ($k1) {
            $rows[] = [
                'ApiKeyId' => 1,
                'JenisApiKey' => 'Gemini',
                'Project' => 'mcmgemini1',
                'ApiKey' => $k1,
                'DefaultVoiceId' => 'Puck',
                'Model' => 'gemini-2.5-pro-preview-tts',
                'RpmLimit' => 60,
                'RpdLimit' => 5000,
                'MinuteCount' => 0,
                'DayCount' => 0,
                'Status' => 'AVAILABLE',
                'CooldownUntilPT' => null,
                'UpdatedAt' => now('UTC'),
                'CreatedAt' => now('UTC'),
            ];
        }
        if ($k2) {
            $rows[] = [
                'ApiKeyId' => 2,
                'JenisApiKey' => 'Gemini',
                'Project' => 'mcmgemini2',
                'ApiKey' => $k2,
                'DefaultVoiceId' => 'Alloy',
                'Model' => 'gemini-2.5-pro-preview-tts',
                'RpmLimit' => 60,
                'RpdLimit' => 5000,
                'MinuteCount' => 0,
                'DayCount' => 0,
                'Status' => 'AVAILABLE',
                'CooldownUntilPT' => null,
                'UpdatedAt' => now('UTC'),
                'CreatedAt' => now('UTC'),
            ];
        }
        if ($k3) {
            $rows[] = [
                'ApiKeyId' => 3,
                'JenisApiKey' => 'OpenAI',
                'Project' => null,
                'ApiKey' => $k3,
                'DefaultVoiceId' => null,
                'Model' => 'gemini-2.0-flash',
                'RpmLimit' => 60,
                'RpdLimit' => 5000,
                'MinuteCount' => 0,
                'DayCount' => 0,
                'Status' => 'AVAILABLE',
                'CooldownUntilPT' => null,
                'UpdatedAt' => now('UTC'),
                'CreatedAt' => now('UTC'),
            ];
        }

        if (!empty($rows)) {
            DB::table('ConfigApiKey')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ConfigApiKey');
    }
};