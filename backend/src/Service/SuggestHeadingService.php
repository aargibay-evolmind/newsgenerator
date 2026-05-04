<?php

namespace App\Service;

class SuggestHeadingService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    /**
     * @param string $title
     * @param array<string> $currentOutline
     * @return string
     */
    public function suggest(?string $title, array $currentOutline): string
    {
        $topic = !empty($title) ? $title : "un artículo general de formación y empleo";
        $outlineStr = !empty($currentOutline) ? implode(', ', $currentOutline) : 'Ninguno';

        $prompt = sprintf(
            "Actúa como un experto en SEO Estratégico. Tengo un artículo titulado o sobre el tema: '%s'.\n" .
            "El esquema actual es: [%s].\n\n" .
            "Sugiere UN solo título de sección (H2) que sea altamente relevante, que no esté ya incluido en el esquema actual y que añada valor real al lector (informativo o transaccional).\n" .
            "Solo devuelve el texto del encabezado, sin comillas ni explicaciones.",
            $topic,
            $outlineStr
        );

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'heading' => [
                    'type' => 'STRING',
                    'description' => 'El texto sugerido para el encabezado.'
                ]
            ],
            'required' => ['heading']
        ];

        $models = ['gemini-3.1-flash-lite-preview', 'gemini-2.0-flash-lite-preview', 'gemini-2.5-flash'];
        $result = $this->gemini->generateContent($prompt, $models, $schema);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonString = $result['candidates'][0]['content']['parts'][0]['text'];
            $data = json_decode($jsonString, true);
            if (isset($data['heading'])) {
                return $data['heading'];
            }
        }

        return "Nuevo Encabezado Sugerido";
    }
}
