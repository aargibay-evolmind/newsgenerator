<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\GetArticlesService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GetArticlesAction
{
    public function __construct(
        private readonly GetArticlesService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/articles', name: 'api_get_articles', methods: ['GET', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        try {
            $articles = $this->domainService->getAll();
            return $this->responder->respond($articles);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
