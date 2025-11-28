<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        $model = $keyRow->Model ?? 'gemini-2.5-flash-preview-tts';

        // Endpoint resmi Gemini TTS
        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":generateSpeech?key=" . $keyRow->ApiKey;

        // Payload sesuai AI Studio
        $payload = [
            "text" => $text,
            "voice" => [
                "voiceName" => $voice ?: ($keyRow->DefaultVoiceId ?? "Puck"),
                "speakingRate" => $speed ?? 1.0
            ],
            "audioConfig" => [
                "audioEncoding" => "MP3"
            ]
        ];

        try {
            $resp = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            // Success
            if ($resp->status() === 200) {
                $data = $resp->json();
                $audio = data_get($data, 'audio.data');
                if ($audio) {
                    return base64_decode($audio);
                }
                return null;
            }

            // Rate limit
            if ($resp->status() === 429) {
                return ['error' => 429];
            }

        } catch (\Throwable $e) {
            // Log error jika perlu
        }

        return null;
    }
}
