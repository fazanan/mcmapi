<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiTtsService
{
    public function synthesize(string $text, string $voice1, string $voice2, $keyRow)
    {
        $model = $keyRow->Model ?? "gemini-2.5-pro-preview-tts";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . urlencode($model)
             . ":generateContent?key={$keyRow->ApiKey}";

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
                "temperature" => 1,
                "responseModalities" => ["audio"],
            ],
            "speechConfig" => [
                "multiSpeakerVoiceConfig" => [
                    "speakerVoiceConfigs" => [
                        [
                            "speaker" => "Speaker 1",
                            "voiceConfig" => [
                                "prebuiltVoiceConfig" => [
                                    "voiceName" => $voice1
                                ]
                            ]
                        ],
                        [
                            "speaker" => "Speaker 2",
                            "voiceConfig" => [
                                "prebuiltVoiceConfig" => [
                                    "voiceName" => $voice2
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $resp = Http::timeout(120)->post($url, $payload);

        if (!$resp->successful()) {
            return [
                "error" => $resp->status(),
                "body"  => $resp->json()
            ];
        }

        $json = $resp->json();

        $base64 = data_get($json,
            "candidates.0.content.parts.0.inlineData.data"
        );

        if (!$base64) {
            return [
                "error" => "NO_AUDIO",
                "body" => $json
            ];
        }

        return base64_decode($base64);
    }
}
