<?php

declare(strict_types=1);

namespace App\Http\Api\Resource;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    /** @return array<string, mixed> */
    abstract public static function openApiSchema(): array;
}
