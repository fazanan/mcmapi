<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        $model = $keyRow->Model ?? 'gemini-2.5-pro-preview-tts';

        // Voice fallback aman
        $voiceName = $voice ?: ($keyRow->DefaultVoiceId ?: 'Verse');

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' 
            . urlencode($model) 
            . ':generateSpeech?key=' 
            . $keyRow->ApiKey;

        $payload = [
            'text' => $text,
            'voiceConfig' => [
                'voiceName' => $voiceName
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => $speed ?? 1.0
            ]
        ];

       $resp = Http::timeout(20)->post($url, $payload);

        // DEBUG: return seluruh response mentah
        return [
            'status' => $resp->status(),
            'body'   => $resp->json(),
        ];


        return null;
    }
}
