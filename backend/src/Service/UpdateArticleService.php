<?php

namespace App\Service;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use stdClass;

class UpdateArticleService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function execute(string $id, stdClass $requestData): Article
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            throw new DomainException(sprintf('Article with ID "%s" not found.', $id));
        }

        if (!isset($requestData->title) || empty(trim($requestData->title))) {
            throw new DomainException('Title cannot be empty.');
        }

        if (!isset($requestData->data) || !is_object($requestData->data)) {
            throw new DomainException('Article data payload must be a valid JSON object.');
        }

        $article->setTitle(trim($requestData->title));
        $article->setData((array) $requestData->data);

        $this->entityManager->flush();

        return $article;
    }
}
