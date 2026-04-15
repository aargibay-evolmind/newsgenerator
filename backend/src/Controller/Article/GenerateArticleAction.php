<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\GenerateArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GenerateArticleAction
{
    public function __construct(
        private readonly GenerateArticleService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/generate-article', name: 'api_generate_article', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);

        // Required base structure
        if (!isset($payload['title']) || !isset($payload['outline'])) {
             return $this->responder->respondError('Title and Outline parameters are required.', 400);
        }

        try {
            $result = $this->domainService->generate($payload);
            return $this->responder->respond($result);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
