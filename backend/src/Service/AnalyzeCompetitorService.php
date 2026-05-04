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

        $prompt .= "Instrucciones de análisis:\n";
        $prompt .= "1. El contenido está estructurado con prefijos como 'H1:', 'H2:', 'H3:' para identificar los encabezados del artículo.\n";
        $prompt .= "2. Usa esos prefijos para identificar la jerarquía de encabezados del cuerpo del artículo (ignora el H1 si es el título principal de la página).\n";
        $prompt .= "3. Extrae ÚNICAMENTE los encabezados H2 (ignora H3, H4 y cualquier nivel inferior). Para cada H2 hallado, extrae su texto exacto y estima cuántas palabras tiene su sección.\n";
        $prompt .= "4. Extrae las 10 palabras clave más relevantes para SEO.\n";
        $prompt .= "5. Genera entre 5 y 7 títulos de sección (H2) ORIGINALES para un artículo sobre el mismo tema. Inspírate en los temas que cubre el competidor para identificar qué aspectos son relevantes, pero redáctalos de forma completamente diferente, más atractiva y optimizada para SEO. NO copies ni parafrasees directamente los títulos del competidor.\n\n[Texto del Artículo]\n" . $competitorText;

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
            ],
            'suggestedHeaders' => [
                'type' => 'ARRAY',
                'description' => 'Lista de 5 a 7 títulos H2 ORIGINALES sugeridos para el artículo del usuario, inspirados en los temas del competidor pero redactados de forma completamente diferente y optimizados para SEO.',
                'items' => ['type' => 'STRING']
            ]
        ];

        $required = ['totalLength', 'keywords', 'headers', 'suggestedHeaders'];

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
