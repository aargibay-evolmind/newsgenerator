<?php

namespace App\Service;

use App\Repository\ArticleRepository;

class GetArticleService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository
    ) {}

    public function getById(string $id): array
    {
        $article = $this->articleRepository->find($id);
        
        if (!$article) {
            throw new \Exception('Article not found', 404);
        }

        return [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'data' => $article->getData(),
            'user_id' => $article->getUserId(),
            'created_at' => $article->getCreatedAt()->format('c'),
            'updated_at' => $article->getUpdatedAt()->format('c')
        ];
    }
}
