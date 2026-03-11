<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonResponder
{
    /**
     * @param mixed $data
     * @param int $status
     * @param array<string, string> $headers
     */
    public function respond(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * @param string $message
     * @param int $status
     */
    public function respondError(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
