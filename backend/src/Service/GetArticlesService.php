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
        $articles = $this->articleRepository->findBy([], ['createdAt' => 'DESC']);
        $result = [];
        foreach ($articles as $article) {
            $result[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'user_id' => $article->getUserId(),
                'data' => $article->getData(),
                'created_at' => $article->getCreatedAt()->format('c'),
                'updated_at' => $article->getUpdatedAt()->format('c')
            ];
        }
        return $result;
    }
}
