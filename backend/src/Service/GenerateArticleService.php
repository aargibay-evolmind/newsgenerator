<?php

namespace App\Service;

class GenerateArticleService
{
    public function __construct(
        private readonly GeminiService $gemini,
        private readonly KnowledgeBaseService $knowledgeBase
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
        $includeLists = $payload['includeLists'] ?? true;
        $includeTables = $payload['includeTables'] ?? false;
        $outline = $payload['outline'] ?? [];
        $references = $payload['references'] ?? [];
        $audience = $payload['audience'] ?? 'General';
        $searchIntent = $payload['searchIntent'] ?? 'Informativo';
        $additionalContext = $payload['additionalContext'] ?? '';
        $keyPoints = $payload['keyPoints'] ?? [];

        // LOAD RELEVANT COURSES (SMART FILTERING)
        $relevantCourses = $this->knowledgeBase->getRelevantCourses($title . ' ' . implode(' ', $keywords) . ' ' . implode(' ', $keyPoints));

        // Configuration translation for tone
        $toneStr = "Neutral / Informativo";
        if ($tone < 30) $toneStr = "Formal, Profesional, y Corporativo.";
        if ($tone > 70) $toneStr = "Cercano, Amigable, y muy Conversacional.";

        // Build the Prompt Guidelines (Unified SEO + Conversion + Structural)
        $prompt = "Eres un **Estratega de Conversión SEO y Orientador Académico Senior** del equipo de ArticleMind, especializado en el ecosistema educativo español (FPs, Certificados y Cursos Técnicos). Tu objetivo es transformar los esquemas técnicos en guías definitivas de alta autoridad que conviertan lectores en alumnos matriculados.\n\n";

        $prompt .= "### I. FILOSOFÍA EDITORIAL Y CONTEXTO 2026 (BASE):\n";
        $prompt .= "- **Actualización 2026-2027:** El contenido debe estar plenamente alineado con la **Nueva Ley de FP** (grados A-E, Dualidad intensiva) y los plazos de admisión vigentes.\n";
        $prompt .= "- **Diferenciación Regional:** Al tratar fechas, becas o requisitos, distingue siempre entre la normativa estatal y las particularidades de las **Comunidades Autónomas** clave (Madrid, Cataluña, Andalucía, Comunidad Valenciana, etc.).\n";
        $prompt .= sprintf("- **Tono Configurado:** %s. Actúa como un 'Career Coach' experto; evita el marketing vacío, ofrece asesoramiento técnico, empático y profesional.\n", $toneStr);

        $prompt .= "\n### II. ESTRUCTURA Y SEO AVANZADO (OBLIGATORIO):\n";
        $prompt .= sprintf("- **Respuesta Directa (AEO):** Bajo el H1 principal, redacta un resumen de máximo 50 palabras que responda la duda central del usuario (para capturar AI Overviews y Snippets).\n");
        $prompt .= "- **Navegación Interactiva (CRÍTICO):** Únicamente después de la 'Respuesta Directa' y antes de la primera sección del esquema, incluye la tabla de contenidos colapsable usando estrictamente: `<details open><summary>Tabla de contenidos</summary>\n\n- [Texto del enlace](#ancla-del-encabezado)\n- ...\n\n</details>`. Asegúrate de que los enlaces sean una **lista con viñetas** Markdown válida y que cada enlace apunte al ID correcto del encabezado. **No la repitas nunca en el cuerpo del texto ni crees un encabezado ## para ella.**\n";
        $prompt .= "- **Autoridad E-E-A-T:** Cita fuentes oficiales (Ministerios, SEPE, BOE, portales regionales). Usa terminología precisa: **nota de corte**, **itinerarios formativos**, **unidades de competencia**.\n";
        $prompt .= "- **Impacto y Empleabilidad:** Siempre que el tema lo permita, incluye datos de mercado laboral 2026 (sectores en auge, salarios medios previstos) para justificar el valor del curso o FP.\n";
        $prompt .= "- **Comparativa Multizona:** Emplea al menos una **tabla Markdown** para comparar plazos, plazas o requisitos entre diferentes CCAA o modalidades.\n";

        $prompt .= "\n### III. DIRECTRICES DE REDACCIÓN Y DATOS:\n";
        $prompt .= sprintf("- **Título Principal:** %s\n", $title);
        $prompt .= sprintf("- **Público Objetivo:** %s\n", $audience);
        $prompt .= sprintf("- **Intención de Búsqueda:** %s\n", $searchIntent);
        
        if (!empty($keyPoints)) {
            $prompt .= sprintf("- **Puntos Críticos a desarrollar:** %s\n", implode(', ', $keyPoints));
        }

        if (!empty($keywords)) {
            $prompt .= sprintf("- **Palabras Clave SEO (integrar naturalmente):** %s\n", implode(', ', $keywords));
        }

        if (!empty(trim($additionalContext))) {
            $prompt .= sprintf("- **Contexto Adicional (Prioridad):** %s\n", $additionalContext);
        }

        // Section Outline
        $prompt .= "\n**IV. ESQUEMA DE SECCIONES (SIGUE ESTRICTAMENTE):**\n";
        foreach ($outline as $item) {
            $budget = $item['budget'] ?? 'medium';
            $budgetInstr = match($budget) {
                'short' => " (Breve y conciso)",
                'long' => " (Extenso, profundo y detallado)",
                default => " (Extensión media)"
            };
            $prompt .= sprintf("- %s%s\n", $item['text'] ?? 'Sección', $budgetInstr);
        }

        // Final Section
        $prompt .= "\n**V. CIERRE E INTERACCIÓN (ORIENTADO A VENTA):**\n";
        $prompt .= "- **FAQ:** Finaliza con una sección de 'Preguntas Frecuentes' sobre acceso, becas o modalidad online.\n";

        if (!empty($relevantCourses)) {
            $prompt .= "- **CTA FINAL (OBLIGATORIO):** En la sección de 'Siguientes Pasos' o en el párrafo final de 'Cierre', incluye de forma explícita y destacada el enlace al curso principal utilizando **exactamente su nombre** como texto del enlace. Por ejemplo: '[Nombre del Curso](URL)'.\n";
            $prompt .= "- **CURSOS SELECCIONADOS:**\n";
            foreach ($relevantCourses as $course) {
                $prompt .= sprintf("  - %s: %s\n", $course['name'], $course['url']);
            }
        } else {
            $prompt .= "- **CTA Contextual:** En las secciones de mayor valor (salarios o requisitos), incluye un puente natural como: '[Solicita información sobre este itinerario aquí](https://davante.es/contacto)'.\n";
        }

        $prompt .= "- **Cierre Persuasivo:** Un párrafo final potente que resalte la urgencia de titularse oficialmente para asegurar el éxito en 2026 e incluya la llamada a la acción al curso mencionado arriba.\n";

        $prompt .= "\n**VI. BLOQUE DE METADATOS (OPTIMIZADO PARA CTR - AL FINAL):**\n";
        $prompt .= "Tras finalizar el artículo, añade el bloque delimitado por ---METADATA---. Maximiza el CTR en buscadores usando palabras de poder y corchetes.\n";
        $prompt .= "---METADATA---\n";
        $prompt .= "Friendly URL: [slug-seo-corto-y-directo]\n";
        $prompt .= "Meta title: [Título con números o corchetes, ej: 'Curso X 2026 [Guía Oficial]']\n";
        $prompt .= "Meta-keywords: [5-10 términos clave]\n";
        $prompt .= "Meta description: [Resumen con beneficio inmediato y llamada a la acción]\n";
        $prompt .= "Short text: [Gancho de 2 frases con promesa de empleabilidad]\n";
        $prompt .= "Email title: [Asunto magnético, ej: '¿Sabías que el sector X paga Y€?']\n";
        $prompt .= "Email text: [Texto breve resaltando un dato impactante e invitando a leer]\n";
        $prompt .= "---METADATA---\n\n";

        if (!empty($references)) {
             $prompt .= "\n**Fuentes de Referencia a integrar (Markdown links):**\n";
             foreach ($references as $ref) {
                 $prompt .= sprintf("- [%s](%s)\n", $ref['title'] ?? 'Referencia', $ref['url'] ?? '#');
             }
        }

        $prompt .= "\n\nRedacta el contenido completo en español utilizando Markdown válido.";

        // Direct call to Gemini WITHOUT schema constraints for free-form markdown response
        $models = ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'];
        $result = $this->gemini->generateContent($prompt, $models);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new \Exception('Failed to generate full markdown article content from AI.');
    }
}
