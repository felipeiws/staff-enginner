<?php

declare(strict_types=1);

namespace App\Http\Api\Request;

use App\Http\Api\Problem\DomainProblem;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    /** @return array{sort:string,id:string}|null */
    protected function cursor(): ?array
    {
        $encoded = $this->query('cursor');
        if ($encoded === null) {
            return null;
        }
        if (! is_string($encoded) || $encoded === '') {
            throw new DomainProblem(400, 'pagination.invalid_cursor', 'O cursor informado é inválido.');
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $cursor = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($cursor) || ! is_string($cursor['sort'] ?? null) || ! is_string($cursor['id'] ?? null)) {
            throw new DomainProblem(400, 'pagination.invalid_cursor', 'O cursor informado é inválido.');
        }

        return ['sort' => $cursor['sort'], 'id' => $cursor['id']];
    }

    protected function perPage(int $default = 25, int $maximum = 100): int
    {
        $value = $this->integer('page_size', $default);
        if ($value < 1 || $value > $maximum) {
            throw new DomainProblem(400, 'pagination.invalid_page_size', "page_size deve estar entre 1 e {$maximum}.");
        }

        return $value;
    }
}
