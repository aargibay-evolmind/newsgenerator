<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\GetArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetArticleAction
{
    public function __construct(
        private readonly GetArticleService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/articles/{id}', name: 'api_get_article', methods: ['GET', 'OPTIONS'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        try {
            $article = $this->domainService->getById($id);
            return $this->responder->respond($article);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return $this->responder->respondError($e->getMessage(), $status);
        }
    }
}
