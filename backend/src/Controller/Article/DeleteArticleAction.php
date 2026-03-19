<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\DeleteArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DeleteArticleAction
{
    public function __construct(
        private readonly DeleteArticleService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/articles/{id}', name: 'api_delete_article', methods: ['DELETE', 'OPTIONS'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        try {
            $this->domainService->delete($id);
            return $this->responder->respond(['message' => 'Article deleted successfully']);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return $this->responder->respondError($e->getMessage(), $status);
        }
    }
}
