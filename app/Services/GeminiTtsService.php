<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, ?string $voice, ?float $speed, $keyRow)
    {
        $model = $keyRow->Model ?? 'gemini-2.5-pro-preview-tts';
        $voiceName = $voice ?: ($keyRow->DefaultVoiceId ?? 'Verse');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":generateContent?key={$keyRow->ApiKey}";

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
                "responseMimeType" => "audio/mp3"
            ],
            "speechConfig" => [
                "voiceConfig" => [
                    "voiceName" => $voiceName
                ],
                "audioConfig" => [
                    "speakingRate" => $speed ?? 1.0
                ]
            ]
        ];

        $resp = Http::timeout(30)->post($url, $payload);

        if ($resp->status() === 429) {
            return [
                'error' => 429,
                'status' => 429,
                'body' => $resp->json()
            ];
        }

        if ($resp->status() !== 200) {
            return [
                'error' => $resp->status(),
                'status' => $resp->status(),
                'body' => $resp->json()
            ];
        }

        // FIX inlineData
        $data = data_get($resp->json(), 'candidates.0.content.parts.0.inlineData.data');

        if ($data) return base64_decode($data);

        return null;
    }
}
