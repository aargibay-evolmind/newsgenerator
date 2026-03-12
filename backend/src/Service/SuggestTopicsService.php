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
            ? "Eres un periodista experto en tendencias de noticias internacionales y de España. Sugiere exactamente 3 titulares de noticias actuales, variados y muy atractivos (trending topics o noticias de interés general) para un blog profesional. Deben ser temas que inviten a hacer clic. Solo devuelve los conceptos/títulos."
            : sprintf(
                "Eres un periodista experto en SEO y marketing de contenidos. Sugiere exactamente 3 titulares o enfoques alternativos atractivos para un artículo sobre: '%s'. Deben ser descriptivos y diseñados para captar clics. Solo devuelve los conceptos/títulos.",
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
