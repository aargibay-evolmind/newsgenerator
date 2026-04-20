<?php

namespace App\Controller\Article;

use App\Service\AnalyzeCompetitorService;
use App\Controller\JsonResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AnalyzeCompetitorAction
{
    public function __construct(
        private readonly AnalyzeCompetitorService $analyzeService,
        private readonly JsonResponder $responder
    ) {
    }

    #[Route('/api/articles/analyze-competitor', name: 'api_articles_analyze_competitor', methods: ['POST', 'OPTIONS'], priority: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['competitorUrl'])) {
                return $this->responder->respondError('Missing competitorUrl in payload', 400);
            }

            $competitorUrl = $data['competitorUrl'];
            $includeMarkdown = isset($data['includeMarkdown']) && $data['includeMarkdown'] === true;
            
            $result = $this->analyzeService->execute($competitorUrl, $includeMarkdown);

            return $this->responder->respond($result);
            
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
