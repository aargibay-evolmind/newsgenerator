<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey
    ) {
    }

    /**
     * @param string $prompt
     * @param string $model
     * @return array<string, mixed>
     */
    public function generateContent(string $prompt, string $model = 'gemini-3-flash-preview', ?array $schema = null): array
    {
        $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $this->geminiApiKey);

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        // If a schema is provided, append it to the configuration to get Structured JSON output
        if ($schema) {
            $body['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema
            ];
        }

        $response = $this->httpClient->request('POST', $url, [
            'json' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            // Timeout settings due to latency in LLM generation
            'timeout' => 60,
        ]);

        return $response->toArray();
    }
}
