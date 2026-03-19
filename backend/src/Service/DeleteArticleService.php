<?php

namespace App\Service;

use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;

class DeleteArticleService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function delete(string $id): void
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            throw new \Exception('Article not found', 404);
        }

        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }
}
