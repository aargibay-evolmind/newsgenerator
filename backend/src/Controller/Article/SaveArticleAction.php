<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\SaveArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SaveArticleAction
{
    public function __construct(
        private readonly SaveArticleService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/articles', name: 'api_save_article', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);

        if (!$payload || !isset($payload['title']) || !isset($payload['data'])) {
             return $this->responder->respondError('Title and Data parameters are required.', 400);
        }

        try {
            $article = $this->domainService->save($payload);
            return $this->responder->respond([
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'created_at' => $article->getCreatedAt()->format('c')
            ], 201);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
