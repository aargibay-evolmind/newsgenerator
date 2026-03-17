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
    public function generate(string $title, array $keyPoints, array $keywords, array $urls, string $audience = 'General', string $searchIntent = 'Informativo', string $additionalContext = ''): array
    {
        $prompt = sprintf(
            "Actúa como el Arquitecto Jefe de contenido para un blog de noticias de alta retención. Vas a diseñar el esquema para un artículo titulado: '%s'.\n",
            $title
        );

        $prompt .= sprintf("**Público Objetivo:** %s\n", $audience);
        $prompt .= sprintf("**Intención de Búsqueda:** %s\n", $searchIntent);

        if (!empty(trim($additionalContext))) {
            $prompt .= sprintf("**Contexto y directrices adicionales del redactor:** %s\n", $additionalContext);
        }

        if (!empty($keywords)) {
            $prompt .= sprintf("**Etiquetas / Keywords (SEO y Categorización):** %s.\n", implode(', ', $keywords));
        }
        
        if (!empty($keyPoints)) {
            $prompt .= sprintf("Debes asegurarte de cubrir obligatoriamente los siguientes Puntos Clave como parte del esquema: %s.\n", implode(', ', $keyPoints));
        }

        $prompt .= "
Requisitos:
1. Diseña un índice (outline) con encabezados muy descriptivos. Devuélvelos en orden lógico.
2. Basado en el tema analizado, propón 3 enlaces hacia páginas de autoridad (ministerios, BOE, organizaciones oficiales, Wikipedia) relevantes que puedan servir como fuentes para el lector. Inventa o infiere las URLs si es necesario, pero mantenlas creíbles.
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
                foreach ($data['outline'] as $index => $item) {
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
