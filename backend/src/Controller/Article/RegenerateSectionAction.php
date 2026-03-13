<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\RegenerateSectionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class RegenerateSectionAction
{
    public function __construct(
        private readonly RegenerateSectionService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/regenerate-section', name: 'api_regenerate_section', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);

        $articleTitle    = $payload['articleTitle'] ?? '';
        $sectionHeading  = $payload['sectionHeading'] ?? '';
        $currentContent  = $payload['currentContent'] ?? '';
        $context         = $payload['context'] ?? '';
        $guidelines      = $payload['guidelines'] ?? '';

        if (empty(trim($sectionHeading))) {
            return $this->responder->respondError('sectionHeading parameter is required.', 400);
        }

        try {
            $newContent = $this->domainService->regenerate($articleTitle, $sectionHeading, $currentContent, $context, $guidelines);
            return $this->responder->respond(['content' => $newContent]);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
