<?php

namespace App\Service;

class GenerateOutlineService
{
    public function __construct(
        private readonly GeminiService $gemini,
        private readonly KnowledgeBaseService $knowledgeBase
    ) {}

    /**
     * @param string $title
     * @param array<string> $keyPoints
     * @param array<string> $keywords
     * @param array<array{url: string, title?: string, content?: string}> $exampleUrls
     * @param array<array{url: string, title?: string, content?: string}> $authorityUrls
     * @param string $audience
     * @param string $searchIntent
     * @param string $additionalContext
     * @param array<string> $masterDLeads
     * @return array<string, mixed>
     */
    public function generate(string $title, array $keyPoints, array $keywords, array $exampleUrls = [], array $authorityUrls = [], string $audience = 'General', string $searchIntent = 'Informativo', string $additionalContext = '', int $tone = 0, int $sectionCount = 7, ?string $contentMode = null, array $masterDLeads = []): array
    {
        $startTime = microtime(true);
        // LOAD RELEVANT COURSES FOR CONTEXTUAL OUTLINE
        $relevantCourses = $this->knowledgeBase->getRelevantCourses($title . ' ' . implode(' ', $keywords) . ' ' . implode(' ', $keyPoints));

        $prompt = sprintf(
            "Actúa como el **Arquitecto Jefe y Estratega de Contenido Académico** de ArticleMind. Tu misión es diseñar la estructura de una guía definitiva titulada: '%s'.\n",
            $title
        );
        $prompt .= "Buscas atraer a potenciales alumnos interesados en formación (FP, cursos, certificados) en España.\n";

        $toneLabel = $tone < 33 ? 'Profesional, autoritario y técnico' : ($tone < 66 ? 'Orientador, cercano y empático' : 'Motivador, dinámico y viral');
        $prompt .= sprintf("**Tono del artículo:** %s (Career Coach).\n", $toneLabel);

        $prompt .= sprintf("**Público Objetivo:** %s\n", $audience);
        $prompt .= sprintf("**Intención de Búsqueda:** %s\n", $searchIntent);

        // Content Mode specific instructions
        if ($contentMode) {
            $modeInstructions = match ($contentMode) {
                'quick-guide' => "**MODO: GUÍA RÁPIDA.** Prioriza la brevedad y escaneabilidad. Los encabezados deben ser directos y orientados a la acción (\"Cómo...\", \"Pasos para...\", \"Requisitos de...\"). Cada sección debe poder leerse de forma independiente. Favorece listas y bullet points sobre párrafos largos.",
                'news-brief' => "**MODO: NOTICIA BREVE.** Usa estructura de pirámide invertida: lo más importante primero. Los encabezados deben ser informativos y concisos. El contenido debe ir de lo general a lo específico. Prioriza datos, cifras y hechos verificables.",
                'deep-dive' => "**MODO: INMERSIVO.** Diseña un artículo exhaustivo y detallado. Los encabezados deben cubrir el tema en profundidad, incluyendo contexto, análisis, comparativas y proyecciones. Cada sección debe ser sustancial con múltiples sub-puntos. Incluye datos, estadísticas y análisis experto.",
                'storytelling' => "**MODO: CRÓNICA.** Estructura narrativa con arco dramático: gancho inicial, desarrollo con tensión, clímax informativo y cierre inspirador. Los encabezados deben ser evocadores y descriptivos, no meramente informativos. Usa títulos que generen curiosidad y emoción.",
                default => ''
            };
            if ($modeInstructions) {
                $prompt .= $modeInstructions . "\n";
            }
        }

        if (!empty(trim($additionalContext))) {
            $prompt .= sprintf("**Directrices de Negocio/Contexto:** %s\n", $additionalContext);
        }

        if (!empty($keywords)) {
            $prompt .= sprintf("**Palabras Clave SEO a cubrir:** %s.\n", implode(', ', $keywords));
        }
        
        if (!empty($keyPoints)) {
            $prompt .= sprintf("**Puntos de Estructura / Temas Críticos:** %s. Integra estos temas de forma natural en el índice como encabezados o sub-apartados.\n", implode(', ', $keyPoints));
        }

        if (!empty($masterDLeads)) {
            $prompt .= sprintf("**Ganchos/Leads de Apertura (Referencia):** %s. No incluyas encabezados para estos ganchos, se usarán solo para la introducción del artículo.\n", implode(', ', $masterDLeads));
        }

        if (!empty($exampleUrls)) {
            $prompt .= "\n### REFERENCIAS DE TONO Y ESTRUCTURA (EJEMPLOS):\n";
            $prompt .= "Analiza el estilo, tono y organización de estos artículos de referencia para aplicarlos a nuestra nueva guía. No copies el contenido, solo emula su enfoque editorial:\n";
            foreach ($exampleUrls as $ref) {
                if (!empty($ref['content'])) {
                    $prompt .= sprintf("- Fuente: %s\n- Contenido a analizar:\n%s\n\n", $ref['title'] ?? $ref['url'], mb_substr($ref['content'], 0, 1500));
                }
            }
        }

        if (!empty($authorityUrls)) {
            $prompt .= "\n### FUENTES DE AUTORIDAD E INFORMACIÓN:\n";
            $prompt .= "Utiliza la siguiente información como fuente verídica para los datos, requisitos y contexto legal/técnico del artículo:\n";
            foreach ($authorityUrls as $ref) {
                if (!empty($ref['content'])) {
                    $prompt .= sprintf("- Fuente: %s\n- Datos clave:\n%s\n\n", $ref['title'] ?? $ref['url'], mb_substr($ref['content'], 0, 2000));
                }
            }
        }

        $prompt .= "
    Requisitos del Esquema:
1. Diseña un índice (outline) con exactamente {$sectionCount} encabezados muy descriptivos y orientados a la conversión. Devuélvelos en orden lógico.
2. **NUNCA incluyas** encabezados para la 'Tabla de contenidos', 'Introducción' o 'Resumen AEO', ya que el sistema los inyecta automáticamente.
3. El esquema debe priorizar la empleabilidad en España 2026 (sueldos, demanda real, requisitos oficiales).
4. **Obligatorio:** Incluye una sección final titulada 'Pasos para empezar tu formación' o similar, diseñada para cerrar con autoridad e invitar a la acción.
5. Propón 3 enlaces de alta autoridad (Ministerios, SEPE, BOE o portales oficiales) relevantes para el tema.
6. **Ganchos/Leads de Apertura:** Revisa los ganchos proporcionados por el usuario (si los hay) y genera exactamente **2 ganchos** optimizados siguiendo esta estructura:
   - **Gancho 1 (Hook):** Una frase o pregunta impactante diseñada para captar la atención inmediata del lector y despertar su curiosidad para seguir leyendo.
   - **Gancho 2 (CTA):** Una frase orientada a la acción (Call to Action) persuasiva que anime al lector a tomar el siguiente paso (por ejemplo, buscar formación, informarse más o dar un giro a su carrera).
   - Refina los ganchos proporcionados por el usuario o genera nuevos si es necesario para cumplir estrictamente con este esquema de 2 ganchos (Hook + CTA).
   - **IMPORTANTE:** No uses negrita (`**`) ni ningún otro formato Markdown en los ganchos.
   - No incluyas llamadas a la acción genéricas.

**IMPORTANTE: Cursos Disponibles en la Academia:**
Considera incluir secciones que faciliten la integración natural de estos cursos específicos en el artículo:
";
        foreach ($relevantCourses as $course) {
            $prompt .= sprintf("- %s\n", $course['name']);
        }
        $prompt .= "\n";

        // Adicionar instrucción sobre influencia de competencia
        if (!empty($exampleUrls)) {
            $prompt .= "10. Marca el campo `influenced_by_competitor` a verdadero (true) ÚNICAMENTE para aquellos encabezados que hayan sido inspirados o basados directamente en la estructura y contenido de los enlaces de REFERENCIAS DE TONO Y ESTRUCTURA (EJEMPLOS) proporcionados.\n";
        }

        $schema = [
            'type' => 'OBJECT', // TYPE_OBJECT from the specification
            'properties' => [
                'outline' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'text' => ['type' => 'STRING', 'description' => 'El título del encabezado.'],
                            'influenced_by_competitor' => ['type' => 'BOOLEAN', 'description' => 'True si este encabezado fue inspirado o basado directamente en la estructura de alguna URL de competencia proporcionada en los ejemplos.']
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
                ],
                'masterDLeads' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                    'description' => 'Lista de exactamente 2 ganchos: el primero es un hook de lectura y el segundo es un CTA.'
                ]
            ],
            'required' => ['outline', 'suggestedLinks', 'masterDLeads']
        ];

        $models = ['gemini-3.1-flash-lite-preview', 'gemini-2.5-flash-lite', 'gemini-2.0-flash-lite-preview', 'gemini-2.5-flash', 'gemini-3.1-pro-preview'];
        $result = $this->gemini->generateContent($prompt, $models, $schema);

        // Parse JSON output
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonString = $result['candidates'][0]['content']['parts'][0]['text'];
            $data = json_decode($jsonString, true);
            
            // Format to the UI expected DTO
            $responseData = [
                'outline' => [],
                'suggestedLinks' => [],
                'masterDLeads' => []
            ];

            if (isset($data['outline']) && is_array($data['outline'])) {
                $outlineItems = array_slice($data['outline'], 0, $sectionCount);
                foreach ($outlineItems as $index => $item) {
                    $responseData['outline'][] = [
                        'id' => time() + $index, // Mocked ID
                        'text' => $item['text'] ?? 'Nuevo Encabezado',
                        'included' => true,
                        'infographic' => false,
                        'budget' => 'medium',
                        'influenced_by_competitor' => $item['influenced_by_competitor'] ?? false
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

            if (isset($data['masterDLeads']) && is_array($data['masterDLeads'])) {
                $responseData['masterDLeads'] = $data['masterDLeads'];
            }

            // APPEND RELEVANT COURSES FROM ACADEMY
            foreach ($relevantCourses as $index => $course) {
                $responseData['suggestedLinks'][] = [
                    'id' => time() + 200 + $index,
                    'title' => "[Curso] " . $course['name'],
                    'url' => $course['url'],
                    'included' => true
                ];
            }

            $endTime = microtime(true);
            $responseData['debug'] = [
                'timeTakenSeconds' => round($endTime - $startTime, 2),
                'textModelUsed' => $result['_usedModel'] ?? 'unknown'
            ];

            return $responseData;
        }

        throw new \Exception('Failed to generate valid outline structure from AI.');
    }
}
