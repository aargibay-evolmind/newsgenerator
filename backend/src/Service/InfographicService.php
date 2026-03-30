<?php

namespace App\Service;

class InfographicService
{
    public function __construct(
        private readonly GeminiService $gemini
    ) {}

    /**
     * @param string $sectionText The title or content of the section
     * @param string $articleTitle The overall article title for context
     * @return string The public URL of the generated infographic
     */
    public function generateForSection(string $sectionText, string $articleTitle): string
    {
        $logFile = '/tmp/infographic_debug.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] InfographicService: Generating for $sectionText\n", FILE_APPEND);

        // 1. Generate Structured Data
        $dataPrompt = sprintf(
            "Extrae y formatea los 3 a 5 puntos clave (datos, cifras o conceptos) más importantes sobre: '%s'. " .
            "Contexto del artículo: '%s'. " .
            "Devuelve ÚNICAMENTE los puntos clave en una lista corta, sin introducciones ni explicaciones adicionales.",
            $sectionText,
            $articleTitle
        );
        file_put_contents($logFile, "InfographicService: Generating structured data...\n", FILE_APPEND);
        
        $structuredData = '';
        try {
            // Using a text model to pre-fetch the data points
            $textResult = $this->gemini->generateContent($dataPrompt, ['gemini-2.5-flash', 'gemini-2.0-flash']);
            if (isset($textResult['candidates'][0]['content']['parts'][0]['text'])) {
                $structuredData = trim($textResult['candidates'][0]['content']['parts'][0]['text']);
                file_put_contents($logFile, "InfographicService: Structured data: \n$structuredData\n", FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents($logFile, "InfographicService: Failed to generate structured data: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        // 2. Generate the visual prompt for Gemini (Short and decoupled from text)
        $visualPrompt = sprintf(
            "Minimalist professional infographic. Theme: %s. Section: %s.",
            $articleTitle,
            $sectionText
        );
        $visualPrompt = substr($visualPrompt, 0, 400);

        file_put_contents($logFile, "InfographicService: Using visual prompt: " . $visualPrompt . "\n", FILE_APPEND);

        // 3. Generate the image via Gemini natively returning Base64
        $base64Image = null;
        $mimeType = 'image/jpeg';
        $lastError = '';
        $models = ['gemini-3.1-flash-image-preview']; 

        foreach ($models as $model) {
            try {
                file_put_contents($logFile, "InfographicService: Attempting image generation with model: $model\n", FILE_APPEND);
                usleep(500000); // 0.5s pause to avoid burst limits
                $imageResult = $this->gemini->generateImage($visualPrompt, $model);
                
                if (isset($imageResult['candidates'][0]['content']['parts'])) {
                    foreach ($imageResult['candidates'][0]['content']['parts'] as $part) {
                        if (isset($part['inlineData']['mimeType']) && str_starts_with($part['inlineData']['mimeType'], 'image/')) {
                            $base64Image = $part['inlineData']['data'];
                            $mimeType = $part['inlineData']['mimeType'];
                            file_put_contents($logFile, "InfographicService: SUCCESS with model $model (mimeType: $mimeType)\n", FILE_APPEND);
                            break 2; // Success!
                        }
                    }
                }
                file_put_contents($logFile, "InfographicService: No image in response from model $model\n", FILE_APPEND);
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                file_put_contents($logFile, "InfographicService: Model $model failed: $lastError\n", FILE_APPEND);
            }
        }
        
        $imageMarkdown = "";
        $imageReference = "";
        if ($base64Image) {
            $imageId = uniqid('infographic_');
            $imageMarkdown = sprintf("![Infografía: %s][%s]", $sectionText, $imageId);
            $imageReference = sprintf("[%s]: data:%s;base64,%s", $imageId, $mimeType, $base64Image);
        } else {
            file_put_contents($logFile, "InfographicService: Gemini failed to generate image. Last Error: $lastError\n", FILE_APPEND);
            $imageMarkdown = sprintf("![Infografía: %s](https://placehold.co/800x450?text=Infografía:+%s)", $sectionText, urlencode($sectionText));
        }

        // 4. Combine Structured Text + Image into a single Markdown block
        $finalMarkdown = sprintf("\n\n### Datos Clave: %s\n\n%s\n\n%s\n\n%s\n", $sectionText, $structuredData, $imageMarkdown, $imageReference);
        return $finalMarkdown;
    }
}
