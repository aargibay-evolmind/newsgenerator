<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class UrlScraperService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * @param string $url
     * @return array<string, string>
     */
    public function scrape(string $url): array
    {
        // Simple scraping strategy just to get the title for preview purposes
        // Real-world implementation would parse article text and metadata
        
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ]
            ]);

            $html = $response->getContent();
            $title = 'Enlace de referencia capturado';

            if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $html, $matches)) {
                $title = trim(strip_tags($matches[1]));
            }

            return [
                'url' => $url,
                'title' => $title
            ];
            
        } catch (\Exception $e) {
            // Provide a fallback response if the scraping fails
            return [
                'url' => $url,
                'title' => parse_url($url, PHP_URL_HOST) ?? 'Enlace de Referencia',
            ];
        }
    }
}
