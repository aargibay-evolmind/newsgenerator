<?php

namespace App\Service;

use App\Entity\Article;
use App\Repository\ArticleRepository;

class SaveArticleService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository
    ) {}

    public function save(array $payload): Article
    {
        $article = new Article();
        $article->setTitle($payload['title'] ?? 'Untitled');
        $article->setData($payload['data'] ?? []);
        $article->setUserId($payload['user_id'] ?? null);

        $this->articleRepository->save($article, true);

        return $article;
    }
}
