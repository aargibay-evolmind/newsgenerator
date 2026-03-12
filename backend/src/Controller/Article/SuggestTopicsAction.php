<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\SuggestTopicsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SuggestTopicsAction
{
    public function __construct(
        private readonly SuggestTopicsService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/suggest-topics', name: 'api_suggest_topics', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);
        $title = $payload['title'] ?? '';

        try {
            $topics = $this->domainService->suggest($title);
            return $this->responder->respond(['topics' => $topics]);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
