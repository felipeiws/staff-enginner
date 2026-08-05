<?php

declare(strict_types=1);

namespace App\Http\Api\Problem;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiProblemResponse
{
    /** @param array<string, mixed> $errors */
    public static function make(int $status, string $code, string $detail, Request $request, string $correlationId, array $errors = []): JsonResponse
    {
        $payload = [
            'type' => sprintf('https://atlas.local/problems/%s', $code),
            'title' => self::title($status),
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getPathInfo(),
            'code' => $code,
            'correlation_id' => $correlationId,
        ];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status)
            ->header('Content-Type', 'application/problem+json')
            ->header('X-Correlation-Id', $correlationId);
    }

    private static function title(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida', 401 => 'Não autenticado', 403 => 'Acesso negado', 406 => 'Representação não aceitável',
            409 => 'Conflito', 415 => 'Tipo de conteúdo não suportado', 422 => 'Entidade não processável', 429 => 'Limite de requisições excedido',
            default => 'Erro interno',
        };
    }
}
