<?php

declare(strict_types=1);

namespace App\Http\Api\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ApiOperation
{
    /**
     * @param  list<string>  $tags
     * @param  array<int|string, string>  $responses
     */
    public function __construct(public readonly string $summary, public readonly array $tags = [], public readonly array $responses = [200 => 'Resposta bem-sucedida']) {}
}
