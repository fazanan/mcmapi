<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        // Model dari database
        $model = $keyRow->Model ?? 'gemini-2.5-flash-preview-tts';

        // Voice fallback
        $voiceName = $voice ?: ($keyRow->DefaultVoiceId ?? 'Verse');

        // Endpoint TTS yang benar (SAMA seperti client)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":generateContent?key={$keyRow->ApiKey}";

        // Payload IDENTIK client
        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        [ "text" => $text ]
                    ]
                ]
            ],
            "generationConfig" => [
                "responseMimeType" => "audio/mp3",
                "voiceConfig" => [
                    "voiceName" => $voiceName
                ],
                "audioConfig" => [
                    "speakingRate" => $speed ?? 1.0
                ]
            ]
        ];

        // Kirim request
        $resp = Http::timeout(30)->post($url, $payload);

        // Jika rate limit
        if ($resp->status() === 429) {
            return [
                'error' => 429,
                'status' => 429,
                'body' => $resp->json()
            ];
        }

        // Jika tidak 200
        if ($resp->status() !== 200) {
            return [
                'error' => $resp->status(),
                'status' => $resp->status(),
                'body' => $resp->json()
            ];
        }

        // Ambil inline_data
        $data = data_get($resp->json(), 'candidates.0.content.parts.0.inline_data.data');

        if ($data) {
            return base64_decode($data);
        }

        // Jika audio tidak ditemukan
        return null;
    }
}
