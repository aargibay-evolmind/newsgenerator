<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\SuggestHeadingService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SuggestHeadingAction
{
    public function __construct(
        private readonly SuggestHeadingService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/articles/suggest-heading', name: 'api_suggest_heading', methods: ['POST', 'OPTIONS'], priority: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);
        $title = $payload['title'] ?? null;
        $currentOutline = $payload['currentOutline'] ?? [];

        try {
            $heading = $this->domainService->suggest($title, $currentOutline);
            return $this->responder->respond(['heading' => $heading]);
        } catch (\Exception $e) {
            // Log the error for internal debugging
            error_log("[SuggestHeadingAction] Error: " . $e->getMessage());
            return $this->responder->respondError("No se pudo generar una sugerencia: " . $e->getMessage(), 500);
        }
    }
}
