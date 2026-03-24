<?php

namespace App\Service;

class KnowledgeBaseService
{
    private string $projectDir;
    private ?array $data = null;
    private ?array $embeddings = null;

    public function __construct(
        string $projectDir,
        private readonly GeminiService $geminiService
    ) {
        $this->projectDir = $projectDir;
    }

    /**
     * Pre-warms the embedding cache to avoid delays during generation.
     * @return array{total: int, synced: int}
     */
    public function syncEmbeddings(): array
    {
        $allCourses = $this->loadData();
        $allEmbeddings = $this->loadEmbeddings();
        $synced = 0;
        $needsSync = false;

        foreach ($allCourses as $course) {
            $contentKey = $course['category'] . '|' . $course['name'];
            
            if (!isset($allEmbeddings[$contentKey])) {
                try {
                    $allEmbeddings[$contentKey] = $this->geminiService->getEmbedding($course['category'] . ': ' . $course['name']);
                    $synced++;
                    $needsSync = true;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        if ($needsSync) {
            $this->saveEmbeddings($allEmbeddings);
        }

        return [
            'total' => count($allCourses),
            'synced' => $synced
        ];
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

        // Ensure cache is ready (minimal check)
        $this->syncEmbeddings();

        // 1. Get embedding for the input context
        try {
            $contextVector = $this->geminiService->getEmbedding($context);
        } catch (\Exception $e) {
            return [];
        }

        $allEmbeddings = $this->loadEmbeddings();
        $scoredCourses = [];

        foreach ($allCourses as $course) {
            $contentKey = $course['category'] . '|' . $course['name'];
            
            if (!isset($allEmbeddings[$contentKey])) {
                continue;
            }

            // 3. Calculate similarity
            $similarity = $this->cosineSimilarity($contextVector, $allEmbeddings[$contentKey]);
            
            if ($similarity > 0.4) {
                $scoredCourses[] = [
                    'score' => $similarity,
                    'course' => $course
                ];
            }
        }

        // Sort by score descending
        usort($scoredCourses, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($item) => $item['course'], array_slice($scoredCourses, 0, 5));
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vec1 as $i => $val) {
            $dotProduct += $val * $vec2[$i];
            $normA += $val * $val;
            $normB += $vec2[$i] * $vec2[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
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

    private function loadEmbeddings(): array
    {
        if ($this->embeddings !== null) {
            return $this->embeddings;
        }

        $filePath = $this->projectDir . '/data/knowledge_base_embeddings.json';
        if (!file_exists($filePath)) {
            return [];
        }

        $json = file_get_contents($filePath);
        $this->embeddings = json_decode($json, true) ?? [];
        return $this->embeddings;
    }

    private function saveEmbeddings(array $embeddings): void
    {
        $filePath = $this->projectDir . '/data/knowledge_base_embeddings.json';
        file_put_contents($filePath, json_encode($embeddings));
        $this->embeddings = $embeddings;
    }
}
