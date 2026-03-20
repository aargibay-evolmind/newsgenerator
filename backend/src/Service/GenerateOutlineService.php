<?php

namespace App\Service;

class GenerateOutlineService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    /**
     * @param string $title
     * @param array<string> $keyPoints
     * @param array<string> $keywords
     * @param array<string> $urls
     * @param string $audience
     * @param string $searchIntent
     * @param string $additionalContext
     * @return array<string, mixed>
     */
    public function generate(string $title, array $keyPoints, array $keywords, array $urls, string $audience = 'General', string $searchIntent = 'Informativo', string $additionalContext = '', int $tone = 0, int $sectionCount = 7): array
    {
        $prompt = sprintf(
            "Actúa como el **Arquitecto Jefe y Estratega de Contenido Académico** de NewsGen. Tu misión es diseñar la estructura de una guía definitiva titulada: '%s'.\n",
            $title
        );
        $prompt .= "Buscas atraer a potenciales alumnos interesados en formación (FP, cursos, certificados) en España.\n";

        $toneLabel = $tone < 33 ? 'Profesional, autoritario y técnico' : ($tone < 66 ? 'Orientador, cercano y empático' : 'Motivador, dinámico y viral');
        $prompt .= sprintf("**Tono del artículo:** %s (Career Coach).\n", $toneLabel);

        $prompt .= sprintf("**Público Objetivo:** %s\n", $audience);
        $prompt .= sprintf("**Intención de Búsqueda:** %s\n", $searchIntent);

        if (!empty(trim($additionalContext))) {
            $prompt .= sprintf("**Directrices de Negocio/Contexto:** %s\n", $additionalContext);
        }

        if (!empty($keywords)) {
            $prompt .= sprintf("**Palabras Clave SEO a cubrir:** %s.\n", implode(', ', $keywords));
        }
        
        if (!empty($keyPoints)) {
            $prompt .= sprintf("Debes integrar obligatoriamente estos Puntos Críticos en el índice: %s.\n", implode(', ', $keyPoints));
        }

        $prompt .= "
Requisitos del Esquema:
1. Diseña un índice (outline) con exactamente {$sectionCount} encabezados muy descriptivos y orientados a la conversión. Devuélvelos en orden lógico.
2. **NUNCA incluyas** encabezados para la 'Tabla de contenidos', 'Introducción' o 'Resumen AEO', ya que el sistema los inyecta automáticamente.
3. El esquema debe priorizar la empleabilidad en España 2026 (sueldos, demanda real, requisitos oficiales).
4. **Obligatorio:** Incluye una sección final de 'Guía de Matriculación' o 'Siguientes Pasos' que invite a la acción.
5. Propón 3 enlaces de alta autoridad (Ministerios, SEPE, BOE o portales oficiales) relevantes para el tema.
";

        $schema = [
            'type' => 'OBJECT', // TYPE_OBJECT from the specification
            'properties' => [
                'outline' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => ['type' => 'STRING', 'description' => 'El título del encabezado.']
                        ]
                    ],
                    'description' => 'Lista de los encabezados del artículo.'
                ],
                'suggestedLinks' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'title' => ['type' => 'STRING', 'description' => 'Título del enlace recomendado.'],
                            'url' => ['type' => 'STRING', 'description' => 'URL oficial hacia el recurso referenciado.']
                        ]
                    ],
                    'description' => 'Lista de enlaces de referencia sugeridos.'
                ]
            ],
            'required' => ['outline', 'suggestedLinks']
        ];

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro'];
        $result = $this->gemini->generateContent($prompt, $models, $schema);

        // Parse JSON output
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonString = $result['candidates'][0]['content']['parts'][0]['text'];
            $data = json_decode($jsonString, true);
            
            // Format to the UI expected DTO
            $responseData = [
                'outline' => [],
                'suggestedLinks' => []
            ];

            if (isset($data['outline']) && is_array($data['outline'])) {
                $outlineItems = array_slice($data['outline'], 0, $sectionCount);
                foreach ($outlineItems as $index => $item) {
                    $responseData['outline'][] = [
                        'id' => time() + $index, // Mocked ID
                        'text' => $item['text'] ?? 'Nuevo Encabezado',
                        'included' => true,
                        'budget' => 'medium'
                    ];
                }
            }

            if (isset($data['suggestedLinks']) && is_array($data['suggestedLinks'])) {
                 foreach ($data['suggestedLinks'] as $index => $link) {
                    $responseData['suggestedLinks'][] = [
                        'id' => time() + 100 + $index, // Mocked ID
                        'title' => $link['title'] ?? 'Enlace Sugerido',
                        'url' => $link['url'] ?? '#',
                        'included' => true
                    ];
                }
            }

            return $responseData;
        }

        throw new \Exception('Failed to generate valid outline structure from AI.');
    }
}
