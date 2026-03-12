<?php

namespace App\Service;

class RegenerateSectionService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    public function regenerate(string $articleTitle, string $sectionHeading, string $currentContent, string $context = ''): string
    {
        $prompt = sprintf(
            "Eres un redactor experto. Dentro del artículo titulado \"%s\", tienes que reescribir únicamente la sección \"%s\".\n\n",
            $articleTitle,
            $sectionHeading
        );

        $prompt .= "El contenido actual de esa sección es:\n\n";
        $prompt .= "---\n" . $currentContent . "\n---\n\n";

        if (!empty(trim($context))) {
            $prompt .= sprintf("Directrices adicionales del redactor: %s\n\n", $context);
        }

        $prompt .= "Instrucciones:\n";
        $prompt .= "- Reescribe únicamente el contenido de esa sección, mejorándolo significativamente.\n";
        $prompt .= "- Mantén el mismo nivel de detalle o aumenta ligeramente la profundidad.\n";
        $prompt .= "- Devuelve SOLO el contenido de la sección en Markdown, SIN incluir el título/encabezado.\n";
        $prompt .= "- No incluyas ningún texto introductorio ni explicativo, solo el contenido en Markdown.\n";

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro'];
        $result = $this->gemini->generateContent($prompt, $models);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }

        throw new \Exception('Failed to regenerate section from AI.');
    }
}
