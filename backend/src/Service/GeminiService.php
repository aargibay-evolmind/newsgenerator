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
     * @param string|array<string> $model Candidates for generation, from primary to fallback.
     * @return array<string, mixed>
     */
    public function generateContent(string $prompt, string|array $model = 'gemini-2.5-flash', ?array $schema = null): array
    {
        $models = is_array($model) ? $model : [$model];
        $lastException = null;

        foreach ($models as $candidate) {
            try {
                return $this->attemptGeneration($prompt, $candidate, $schema);
            } catch (\Exception $e) {
                $lastException = $e;
                // Retry only on rate limit (429) or server error (503)
                if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), '503')) {
                    continue;
                }
                throw $e;
            }
        }

        throw new \Exception(sprintf(
            "All candidate models failed. Last error: %s",
            $lastException ? $lastException->getMessage() : 'Unknown error'
        ));
    }

    private function attemptGeneration(string $prompt, string $model, ?array $schema): array
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
            'timeout' => 60,
        ]);

        return $response->toArray();
    }
}
