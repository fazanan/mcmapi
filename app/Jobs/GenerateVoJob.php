<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\ApiKeySelector;
use App\Services\GeminiTtsService;

class GenerateVoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
        $this->onQueue('tts');
    }

    public function handle(): void
    {
        $row = DB::table('voice_jobs')->where('id', $this->jobId)->first();
        if (!$row) return;
        DB::table('voice_jobs')->where('id', $this->jobId)->update(['status' => 'processing', 'updated_at' => now()]);

        $selector = new ApiKeySelector();
        $key = $selector->select();
        if (!$key) {
            DB::table('voice_jobs')->where('id', $this->jobId)->update(['status' => 'no_key', 'updated_at' => now()]);
            $this->release(5);
            return;
        }

        $svc = new GeminiTtsService();
        $audio = $svc->synthesize((string)$row->text, (string)($row->voice ?? null), (float)($row->speed ?? 1.0), $key);
        if (is_array($audio) && ($audio['error'] ?? null) === 429) {
            $selector->mark429($key->ApiKeyId);
            DB::table('voice_jobs')->where('id', $this->jobId)->update(['status' => 'retry', 'updated_at' => now()]);
            $this->release(3);
            return;
        }
        if (!$audio) {
            DB::table('voice_jobs')->where('id', $this->jobId)->update(['status' => 'failed', 'updated_at' => now()]);
            return;
        }

        Storage::disk('public')->put('vo/'.$this->jobId.'.mp3', $audio);
        $selector->incrementUsage($key->ApiKeyId);
        DB::table('voice_jobs')->where('id', $this->jobId)->update([
            'status' => 'done',
            'path' => 'vo/'.$this->jobId.'.mp3',
            'updated_at' => now(),
        ]);
    }
}