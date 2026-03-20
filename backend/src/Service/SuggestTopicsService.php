<?php

namespace App\Service;

class SuggestTopicsService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    /**
     * @param string $topic
     * @return array<string>
     */
    public function suggest(string $topic): array
    {
        $prompt = empty(trim($topic))
            ? "Eres un **Estratega de Crecimiento Académico en España**. Sugiere exactamente 3 titulares de noticias o guías de formación que estén marcando tendencia ahora mismo (nuevas convocatorias de FP, profesiones con salarios >30k€ en 2026 o formación con empleabilidad garantizada). Deben ser magnéticos, ultra-clicables y resaltar el beneficio económico o profesional inmediato. Solo devuelve los conceptos/títulos."
            : sprintf(
                "Eres un **Estratega SEO Educativo**. Sugiere exactamente 3 titulares competitivos y altamente atractivos para un artículo sobre: '%s'. Deben enfocarse en la resolución de dudas críticas, el aumento salarial por titulación oficial o el éxito laboral 2026. Diseñados para convertir lectores en potenciales alumnos. Solo devuelve los conceptos/títulos.",
                $topic
            );

        $schema = [
            'type' => 'OBJECT', // TYPE_OBJECT from the specification
            'properties' => [
                'topics' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'STRING'
                    ],
                    'description' => 'Lista de los títulos o enfoques sugeridos.'
                ]
            ],
            'required' => ['topics']
        ];

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash-lite', 'gemini-2.0-flash'];
        $result = $this->gemini->generateContent($prompt, $models, $schema);

        // Parse the deep nested structure response of the REST API
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonString = $result['candidates'][0]['content']['parts'][0]['text'];
            $data = json_decode($jsonString, true);
            if (isset($data['topics']) && is_array($data['topics'])) {
                return $data['topics'];
            }
        }

        throw new \Exception('Failed to generate valid topics structure from AI.');
    }
}
