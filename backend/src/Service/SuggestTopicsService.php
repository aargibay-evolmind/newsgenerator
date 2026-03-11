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
        $prompt = sprintf(
            "Eres un periodista experto en SEO. Necesito que sugieras 5 titulares o enfoques alternativos atractivos para un artículo de blog sobre el siguiente tema: '%s'. Deben ser descriptivos y diseñados para captar clics. Solo devuelve los conceptos/títulos.",
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

        $result = $this->gemini->generateContent($prompt, 'gemini-3-flash-preview', $schema);

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
