<?php

namespace App\Service;

class GenerateArticleService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    public function generate(array $payload): string
    {
        $title = $payload['title'] ?? 'Sin Título';
        $keywords = $payload['keywords'] ?? [];
        $tone = (int) ($payload['tone'] ?? 50);
        $length = $payload['articleLength'] ?? 'medium';
        $includeLists = $payload['includeLists'] ?? true;
        $includeTables = $payload['includeTables'] ?? false;
        $outline = $payload['outline'] ?? [];
        $references = $payload['references'] ?? [];
        $audience = $payload['audience'] ?? 'General';
        $searchIntent = $payload['searchIntent'] ?? 'Informativo';
        $additionalContext = $payload['additionalContext'] ?? '';

        // Build the Prompt Guidelines
        $prompt = "Eres un redactor experto y periodista de un blog de alta autoridad. Redactarás un artículo final optimizado para SEO, formateado estrictamente en formato Markdown estándar.\n\n";

        $prompt .= sprintf("**Principios editoriales que debes seguir:**\n");
        $prompt .= sprintf("- estructura jerárquica clara (H1, H2, H3)\n");
        $prompt .= sprintf("- introducción que contextualice el tema\n");
        $prompt .= sprintf("- desarrollo progresivo del contenido\n");
        $prompt .= sprintf("- uso de listas para mejorar escaneabilidad\n");
        $prompt .= sprintf("- secciones conectadas de forma lógica\n");
        $prompt .= sprintf("- conclusión que sintetice los puntos clave\n");
        
        $prompt .= sprintf("**Tema Principal y Título sugerido:** %s\n", $title);
        $prompt .= sprintf("**Público Objetivo:** %s\n", $audience);
        $prompt .= sprintf("**Intención de Búsqueda:** %s\n", $searchIntent);
        $prompt .= "\n**Instrucción de Navegación (CRÍTICO):**\n";
        $prompt .= "- Después de la introducción, incluye una 'Tabla de contenidos' colapsable usando las etiquetas HTML `<details open>` y `<summary>`. No añadas un encabezado Markdown (# o ##) para esta tabla, usa la etiqueta `<summary>` con el texto 'Tabla de contenidos'.\n";
        $prompt .= "- Dentro del `<details>`, crea una lista Markdown de enlaces internos a todos los encabezados H2 del artículo (ej: `[Título de la sección](#titulo-de-la-sección)`).\n";

        if (!empty(trim($additionalContext))) {
            $prompt .= sprintf("**Contexto y directrices adicionales del redactor (sigue estas instrucciones con prioridad):** %s\n", $additionalContext);
        }
        
        if (!empty($keywords)) {
            $prompt .= sprintf("**Palabras/Conceptos Clave a integrar:** %s\n", implode(', ', $keywords));
        }

        // Configuration translation
        $toneStr = "Neutral / Informativo";
        if ($tone < 30) $toneStr = "Formal, Profesional, y Corporativo.";
        if ($tone > 70) $toneStr = "Cercano, Amigable, y muy Conversacional.";
        $prompt .= sprintf("**Tono del artículo:** %s\n", $toneStr);

        $lengthStr = "Aproximadamente 700 palabras.";
        if ($length === 'short') $lengthStr = "Breve y al grano. Aproximadamente 400 palabras.";
        if ($length === 'long') $lengthStr = "Extenso y detallado. Más de 1000 palabras.";
         $prompt .= sprintf("**Longitud objetivo:** %s\n", $lengthStr);

        if ($includeLists) $prompt .= "- Es fundamental incluir listas de viñetas o numeradas para desglose de información.\n";
        if ($includeTables) $prompt .= "- Es fundamental emplear al menos una tabla Markdown para comparar o presentar datos de forma estructurada.\n";

        // Inject the strictly allowed outline
        $prompt .= "\n**Estructura del Artículo (Sigue este índice exacto como encabezados principales):**\n";
        foreach ($outline as $item) {
            $budget = $item['budget'] ?? 'medium';
            $budgetInstr = "";
            if ($budget === 'short') $budgetInstr = " (Breve y conciso)";
            if ($budget === 'long') $budgetInstr = " (Extenso, profundo y detallado)";
            
            $prompt .= sprintf("- %s%s\n", $item['text'] ?? 'Sección', $budgetInstr);
        }

        // Integrate references into content if chosen
        if (!empty($references)) {
             $prompt .= "\n**Fuentes de Autoridad a integrar de forma natural como enlaces (Markdown links):**\n";
             foreach ($references as $ref) {
                 $prompt .= sprintf("- [%s](%s)\n", $ref['title'] ?? 'Referencia', $ref['url'] ?? '#');
             }
        }

        $prompt .= "\n\nRedacta el contenido en español utilizando saltos de línea y Markdown válido.";

        // Direct call to Gemini WITHOUT schema constraints for free-form markdown response
        $models = ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'];
        $result = $this->gemini->generateContent($prompt, $models);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new \Exception('Failed to generate full markdown article content from AI.');
    }
}
