<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\UrlScraperService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ScrapeUrlAction
{
    public function __construct(
        private readonly UrlScraperService $domainService,
        private readonly JsonResponder $responder
    ) {}

    #[Route('/api/scrape-urls', name: 'api_scrape_urls', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);
        $url = $payload['url'] ?? '';

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->responder->respondError('Invalid URL provided.', 400);
        }

        try {
            $data = $this->domainService->scrape($url);
            return $this->responder->respond($data);
        } catch (\Exception $e) {
            return $this->responder->respondError($e->getMessage(), 500);
        }
    }
}
