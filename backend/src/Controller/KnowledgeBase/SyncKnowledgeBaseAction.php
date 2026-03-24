<?php

namespace App\Controller\KnowledgeBase;

use App\Service\KnowledgeBaseService;
use App\Controller\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/knowledge-base/sync', name: 'app_kb_sync', methods: ['POST'])]
class SyncKnowledgeBaseAction extends AbstractController
{
    public function __construct(
        private readonly KnowledgeBaseService $kbService,
        private readonly JsonResponder $responder
    ) {}

    public function __invoke(): JsonResponse
    {
        $result = $this->kbService->syncEmbeddings();
        return $this->responder->success($result);
    }
}
