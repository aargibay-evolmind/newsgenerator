<?php

namespace App\Controller\Article;

use App\Controller\JsonResponder;
use App\Service\UpdateArticleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use DomainException;
use Throwable;

class UpdateArticleAction
{
    public function __construct(
        private readonly UpdateArticleService $updateArticleService,
        private readonly JsonResponder $responder
    ) {
    }

    #[Route('/api/articles/{id}', name: 'update_article', methods: ['PUT'])]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            if (!Uuid::isValid($id)) {
                return $this->responder->respondError('Invalid Article ID format.', Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->responder->respondError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
            }

            $input = json_decode(json_encode($data), false); // Convert to object

            $article = $this->updateArticleService->execute($id, $input);

            return $this->responder->respond([
                'id' => (string) $article->getId(),
                'title' => $article->getTitle(),
                'data' => $article->getData(),
                'user_id' => $article->getUserId(),
                'created_at' => $article->getCreatedAt()->format('c'),
                'updated_at' => $article->getUpdatedAt()->format('c')
            ]);
        } catch (DomainException $e) {
            return $this->responder->respondError($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            return $this->responder->respondError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
