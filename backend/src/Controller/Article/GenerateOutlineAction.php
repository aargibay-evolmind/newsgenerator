<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\GenerateOutlineService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GenerateOutlineAction
{
    public function __construct(
        private readonly GenerateOutlineService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/generate-outline', name: 'api_generate_outline', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);
        
        $title = $payload['title'] ?? '';
        $keywords = $payload['keywords'] ?? [];
        $urls = $payload['referenceUrls'] ?? [];

        if (empty(trim($title))) {
            return $this->responder->respondError('Title parameter is required.', 400);
        }

        try {
            $data = $this->domainService->generate($title, $keywords, $urls);
            return $this->responder->respond($data);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), 500);
        }
    }
}
