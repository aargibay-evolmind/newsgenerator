<?php

namespace App\Service;

class RegenerateSectionService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    public function regenerate(string $articleTitle, string $sectionHeading, string $currentContent, string $context = '', string $guidelines = ''): string
    {
        $prompt = sprintf(
            "Eres un **Estratega de Contenido Senior** de NewsGen. Tu tarea es reescribir con máxima autoridad y precisión la sección \"%s\" del artículo \"%s\".\n\n",
            $sectionHeading,
            $articleTitle
        );

        $prompt .= "El contenido actual es:\n";
        $prompt .= "---\n" . $currentContent . "\n---\n\n";

        if (!empty(trim($guidelines))) {
            $prompt .= sprintf("**DIRECTRICES ESPECÍFICAS DE MEJORA (Prioridad):** %s\n\n", $guidelines);
        }

        $prompt .= "Instrucciones de Redacción:\n";
        $prompt .= "- Mejora el estilo siguiendo un tono experto, orientador y profesional (SEO 2026/2027).\n";
        $prompt .= "- **Enfoque en Conversión:** Si la sección lo permite, resalta beneficios laborales o el valor de la formación oficial.\n";
        $prompt .= "- Integra datos, beneficios o referencias oficiales si el contexto lo permite.\n";
        $prompt .= "- Devuelve solo el Markdown del cuerpo de la sección, SIN el título del encabezado.\n";
        $prompt .= "- Prohibido incluir notas, introducciones o textos fuera del contenido final.\n";

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro'];
        $result = $this->gemini->generateContent($prompt, $models);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }

        throw new \Exception('Failed to regenerate section from AI.');
    }
}
