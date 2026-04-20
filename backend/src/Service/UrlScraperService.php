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
    public function scrape(string $url, int $limit = 5000): array
    {
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

            // Extract content: Remove script, style, and nav tags
            $content = preg_replace('/<(script|style|nav|footer|header|aside).*?<\/ \1>/ims', '', $html);
            $content = strip_tags($content, '<h1><h2><h3><h4><p><ul><ol><li><strong><em>');
            $content = html_entity_decode($content);
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            // Limit content length to avoid hitting token limits early
            $content = mb_substr($content, 0, $limit);

            return [
                'url' => $url,
                'title' => $title,
                'content' => $content
            ];
            
        } catch (\Exception $e) {
            // Provide a fallback response if the scraping fails
            return [
                'url' => $url,
                'title' => parse_url($url, PHP_URL_HOST) ?? 'Enlace de Referencia',
                'content' => ''
            ];
        }
    }
}
