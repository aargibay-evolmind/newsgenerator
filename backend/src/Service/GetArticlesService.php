<?php

namespace App\Service;

use App\Repository\ArticleRepository;

class GetArticlesService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository
    ) {}

    public function getAll(): array
    {
        $conn = $this->articleRepository->getEntityManager()->getConnection();
        
        $sql = "
            SELECT 
                BIN_TO_UUID(id) as id, 
                title, 
                user_id, 
                created_at, 
                updated_at,
                JSON_EXTRACT(data, '$.readingTime') as readingTime,
                JSON_EXTRACT(data, '$.tone') as tone,
                JSON_EXTRACT(data, '$.keywords') as keywords,
                blog_id
            FROM article
        ";
        
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $articles = $resultSet->fetchAllAssociative();
        
        $result = [];
        foreach ($articles as $row) {
            // Ensure keywords is always an array to prevent frontend crashes (.slice error)
            $keywords = [];
            if ($row['keywords']) {
                $decoded = json_decode($row['keywords'], true);
                if (is_array($decoded)) {
                    $keywords = $decoded;
                }
            }

            $result[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'user_id' => $row['user_id'],
                'blog_id' => $row['blog_id'] ? (int) $row['blog_id'] : null,
                'data' => [
                    'readingTime' => $row['readingTime'] ? json_decode($row['readingTime']) : 0,
                    'tone' => $row['tone'] ? json_decode($row['tone']) : null,
                    'keywords' => $keywords
                ],
                'created_at' => (new \DateTimeImmutable($row['created_at']))->format('c'),
                'updated_at' => (new \DateTimeImmutable($row['updated_at']))->format('c')
            ];
        }

        // Sort in PHP to avoid MySQL 'Out of sort memory' error with huge rows
        usort($result, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
        
        return $result;
    }
}
