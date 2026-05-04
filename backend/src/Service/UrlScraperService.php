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

            // Use DOMDocument for reliable parsing
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR);

            // Remove noisy elements: scripts, styles, nav, header, footer, aside
            $tagsToRemove = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'noscript', 'form', 'button'];
            foreach ($tagsToRemove as $tag) {
                foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }

            // Extract structured content: headings and paragraphs with newlines
            $lines = [];
            $contentTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'p', 'li'];
            foreach ($contentTags as $tag) {
                foreach ($dom->getElementsByTagName($tag) as $node) {
                    $text = trim($node->textContent);
                    if (strlen($text) > 10) { // filter out tiny noise snippets
                        $prefix = in_array($tag, ['h1','h2','h3','h4','h5']) ? strtoupper($tag) . ': ' : '';
                        $lines[] = $prefix . $text;
                    }
                }
            }

            $content = implode("\n", $lines);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
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
