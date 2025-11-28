<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, string $voice, $keyRow)
    {
        // MODEL YANG VALID UNTUK REST
        $model = 'gemini-2.5-flash-tts';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$keyRow->ApiKey}";

        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $text]
                    ]
                ]
            ],

            "generationConfig" => [
                "responseModalities" => ["audio"],
                "temperature" => 1,
                "speech_config" => [
                    "voice_config" => [
                        "prebuilt_voice_config" => [
                            "voice_name" => $voice
                        ]
                    ]
                ]
            ]
        ];

        try {
            $resp = Http::withOptions(['stream' => true])
                ->timeout(60)
                ->post($url, $payload);

            if ($resp->status() === 429) {
                return ['error' => 429, 'body' => null];
            }

            if ($resp->status() !== 200) {
                return [
                    'error' => $resp->status(),
                    'body' => $resp->json()
                ];
            }

            // STREAM PARSING
            $audioData = '';
            foreach ($resp->toPsrResponse()->getBody() as $chunk) {
                $j = json_decode($chunk, true);
                $b64 = data_get($j, 'candidates.0.content.parts.0.inlineData.data');

                if ($b64) {
                    $audioData .= base64_decode($b64);
                }
            }

            return $audioData ?: ['error' => 'no_audio_stream'];
        }

        catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
    }
}
