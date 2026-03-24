<?php

namespace App\Service;

class SuggestTopicsService
{
    public function __construct(
        private readonly GeminiService $gemini,
        private readonly KnowledgeBaseService $knowledgeBase
    ) {}

    /**
     * @param string $topic
     * @return array<string>
     */
    public function suggest(string $topic): array
    {
        // Fetch a sample of courses to give the AI context of what we sell
        $courses = $this->knowledgeBase->getRelevantCourses($topic ?: 'FP Cursos Profesionales');
        $coursesStr = "";
        foreach ($courses as $c) {
            $coursesStr .= "- {$c['name']} ({$c['category']})\n";
        }

        $prompt = "Eres un **Estratega de Crecimiento Académico en España**. Tu misión es sugerir 3 titulares de alto impacto.\n\n";
        
        if (!empty($coursesStr)) {
            $prompt .= "**CURSOS DISPONIBLES EN NUESTRA ACADEMIA:**\n" . $coursesStr . "\n";
            $prompt .= "IMPORTANTE: Al menos 2 de los 3 temas sugeridos deben estar directamente relacionados con estos cursos para facilitar la venta.\n\n";
        }

        $prompt .= "### CATEGORÍAS DE INTENCIÓN REQUERIDAS:\n";
        $prompt .= "1. **Informativo/Tendencia:** (Ej: 'Nuevas becas FP 2026' o 'Cambios en la Ley de Educación').\n";
        $prompt .= "2. **Comercial/Transaccional:** (Ej: 'Los 5 cursos con más salida' o '¿Cuánto gana un Técnico en X?').\n";
        $prompt .= "3. **Guía de Autoridad:** (Ej: 'Cómo conseguir tu título oficial en menos de 1 año').\n\n";

        $prompt .= sprintf(
            "Sugiere 3 titulares magnéticos para el tema: '%s'. Deben ser ultra-clicables, resaltar el beneficio económico (sueldos) o profesional (empleo) y estar optimizados para SEO 2026. Solo devuelve los conceptos/títulos.",
            $topic ?: "Tendencias en Formación y Empleo 2026"
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
