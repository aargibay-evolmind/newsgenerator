<?php

namespace App\Service;

class KnowledgeBaseService
{
    private string $projectDir;
    private ?array $data = null;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * @return array<array{category: string, name: string, url: string}>
     */
    public function getRelevantCourses(string $context): array
    {
        $allCourses = $this->loadData();
        if (empty($allCourses)) {
            return [];
        }

        $contextNormalized = $this->normalize($context);
        $contextWords = array_filter(explode(' ', $contextNormalized), fn($w) => strlen($w) > 3);
        
        $scoredCourses = [];

        foreach ($allCourses as $course) {
            $score = 0;
            $nameNormalized = $this->normalize($course['name']);
            $categoryNormalized = $this->normalize($course['category']);
            $courseWords = explode(' ', $nameNormalized);

            foreach ($contextWords as $ctxWord) {
                // 1. Exact word match (High priority)
                if (in_array($ctxWord, $courseWords)) {
                    $score += 25;
                    continue;
                }

                // 2. Strong partial match (e.g. "mecanic" matching "mecanico" or "mecanica")
                $ctxRoot = mb_substr($ctxWord, 0, 6);
                foreach ($courseWords as $cWord) {
                    if (strlen($cWord) > 4 && str_starts_with($cWord, $ctxRoot)) {
                        $score += 15;
                    }
                }

                // 3. Category relevance
                if (str_contains($categoryNormalized, $ctxWord)) {
                    $score += 10;
                }
            }

            if ($score > 0) {
                $scoredCourses[] = ['score' => $score, 'course' => $course];
            }
        }

        // Sort by score descending
        usort($scoredCourses, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top 5 courses
        return array_map(fn($item) => $item['course'], array_slice($scoredCourses, 0, 5));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $chars = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u'
        ];
        return strtr($text, $chars);
    }

    private function loadData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $filePath = $this->projectDir . '/data/knowledge_base.json';
        if (!file_exists($filePath)) {
            return [];
        }

        $json = file_get_contents($filePath);
        $decoded = json_decode($json, true);

        $this->data = $decoded['courses'] ?? [];
        return $this->data;
    }
}
