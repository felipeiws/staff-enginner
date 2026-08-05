<?php

declare(strict_types=1);

namespace App\Http\Api\Pagination;

use App\Http\Api\Problem\DomainProblem;

final class StableCursor
{
    /** @param array{sort:string,id:string} $position */
    public static function encode(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{sort:string,id:string} */
    public static function decode(string $cursor): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $position = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($position) || ! is_string($position['sort'] ?? null) || ! is_string($position['id'] ?? null)) {
            throw new DomainProblem(400, 'pagination.invalid_cursor', 'O cursor informado é inválido.');
        }

        return ['sort' => $position['sort'], 'id' => $position['id']];
    }
}
