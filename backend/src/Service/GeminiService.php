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
    public function generateContent(string $prompt, string|array $model = 'gemini-3-flash-preview', ?array $schema = null): array
    {
        $models = is_array($model) ? $model : [$model];
        $lastException = null;
        $logFile = __DIR__ . '/../../var/log/gemini.log';

        foreach ($models as $candidate) {
            $retries = 0;
            $maxRetries = 2; // Retry each model up to 2 times
            
            while ($retries <= $maxRetries) {
                try {
                    $response = $this->attemptGeneration($prompt, $candidate, $schema);
                    $response['_usedModel'] = $candidate;
                    return $response;
                } catch (\Exception $e) {
                    $errorMsg = sprintf("[%s] %s", $candidate, $e->getMessage());
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Gemini ERROR: $errorMsg\n", FILE_APPEND);
                    $lastException = new \Exception($errorMsg, $e->getCode(), $e);
                    
                    $isRateLimit = str_contains($e->getMessage(), '429');
                    $isServerError = str_contains($e->getMessage(), '500') || str_contains($e->getMessage(), '503');
                    
                    if ($isRateLimit || $isServerError) {
                        $retries++;
                        if ($retries <= $maxRetries) {
                            // Wait longer if it's a rate limit
                            $waitTime = $isRateLimit ? 3 : 1;
                            sleep($waitTime);
                            continue;
                        }
                    }
                    // If not retryable or max retries reached, move to next model
                    break;
                }
            }
            // Small gap between different models to avoid bursting
            usleep(500000); // 0.5s
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

    /**
     * @param string $text
     * @param string $model
     * @return array<float>
     */
    public function getEmbedding(string $text, string $model = 'text-embedding-004'): array
    {
        $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent?key=%s', $model, $this->geminiApiKey);

        $body = [
            'model' => 'models/' . $model,
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ]
        ];

        $response = $this->httpClient->request('POST', $url, [
            'json' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);

        $data = $response->toArray();

        if (isset($data['embedding']['values'])) {
            return $data['embedding']['values'];
        }

        throw new \Exception('Failed to generate embedding from Gemini API.');
    }

    /**
     * @param array<string> $texts
     * @param string $model
     * @return array<array<float>>
     */
    public function getBatchEmbeddings(array $texts, string $model = 'text-embedding-004'): array
    {
        $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:batchEmbedContents?key=%s', $model, $this->geminiApiKey);

        $requests = [];
        foreach ($texts as $text) {
            $requests[] = [
                'model' => 'models/' . $model,
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
        }

        $response = $this->httpClient->request('POST', $url, [
            'json' => ['requests' => $requests],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray();

        if (isset($data['embeddings'])) {
            return array_map(fn($e) => $e['values'], $data['embeddings']);
        }

        throw new \Exception('Failed to generate batch embeddings from Gemini API.');
    }

    /**
     * @param string $prompt
     * @param string $model
     * @return array<string, mixed>
     */
    public function generateImage(string $prompt, string $model = 'gemini-2.0-flash-exp'): array
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
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE']
            ]
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            return $response->toArray();
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            $errorBody = "No response body available from server.";
            if (method_exists($e, 'getResponse')) {
                try {
                    $errorBody = $e->getResponse()->getContent(false);
                } catch (\Exception $inner) {}
            }
            throw new \Exception(sprintf('%s | Server Response Body: %s', $e->getMessage(), $errorBody), 0, $e);
        }
    }
}


