<?php

namespace App\Service;

class AnalyzeCompetitorService
{
    public function __construct(
        private readonly UrlScraperService $scraperService,
        private readonly GeminiService $geminiService
    ) {
    }

    /**
     * @param string $competitorUrl
     * @param bool $includeMarkdown
     * @return array<string, mixed>
     */
    public function execute(string $competitorUrl, bool $includeMarkdown = false): array
    {
        // 1. Get competitor text with a higher limit (up to 50k chars)
        $scrapedData = $this->scraperService->scrape($competitorUrl, 50000);
        
        $competitorText = $scrapedData['content'];
        if (empty(trim($competitorText))) {
            throw new \Exception("Could not extract meaningful content from the competitor URL.");
        }

        // 2. Call Gemini for analysis
        $prompt = "Actúa como un experto analista SEO y parser de contenido. Analiza el siguiente artículo extraído de la competencia y devuelve los datos estructurados en formato JSON.\n";
        
        if ($includeMarkdown) {
            $prompt .= "Extrae el contenido completo y conviértelo a Markdown puro.\n";
        }

        $prompt .= "Identifica todos los encabezados del artículo, estima la longitud (en número de palabras) de la sección que le sigue a cada encabezado, la longitud total del artículo, y las 3-5 palabras clave principales.
        
        [Artículo Competencia]
        " . $competitorText;

        $properties = [
            'totalLength' => ['type' => 'INTEGER', 'description' => 'Número total de palabras estimadas del contenido.'],
            'keywords' => [
                'type' => 'ARRAY',
                'items' => ['type' => 'STRING']
            ],
            'headers' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'text' => ['type' => 'STRING', 'description' => 'El texto del encabezado (ej. H2, H3).'],
                        'length' => ['type' => 'INTEGER', 'description' => 'Estimación de palabras de la sección que pertenece a este encabezado.']
                    ]
                ]
            ]
        ];

        $required = ['totalLength', 'keywords', 'headers'];

        if ($includeMarkdown) {
            $properties['markdown'] = ['type' => 'STRING', 'description' => 'El contenido completo formateado en Markdown.'];
            $required[] = 'markdown';
        }

        $schema = [
            'type' => 'OBJECT',
            'properties' => $properties,
            'required' => $required
        ];

        $models = ['gemini-3.1-flash-lite-preview', 'gemini-2.5-flash-lite', 'gemini-2.0-flash-lite-preview', 'gemini-2.5-flash', 'gemini-2.0-flash'];
        $response = $this->geminiService->generateContent($prompt, $models, $schema);

        $jsonText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $result = json_decode($jsonText, true) ?? [];

        // Append the original competitor URL to the result so the frontend knows what was analyzed
        $result['competitor_url'] = $competitorUrl;

        return $result;
    }
}
