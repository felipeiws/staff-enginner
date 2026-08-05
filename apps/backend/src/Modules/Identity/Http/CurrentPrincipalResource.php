<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Http;

use App\Http\Api\Resource\ApiResource;
use Illuminate\Http\Request;

final class CurrentPrincipalResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['principal' => ['id' => $this->resource->id, 'type' => $this->resource->type, 'name' => $this->resource->displayName, 'avatar_initials' => $this->resource->avatarInitials]];
    }

    public static function openApiSchema(): array
    {
        return ['type' => 'object', 'required' => ['principal'], 'properties' => ['principal' => ['type' => 'object', 'required' => ['id', 'type', 'name', 'avatar_initials'], 'properties' => ['id' => ['type' => 'string'], 'type' => ['type' => 'string'], 'name' => ['type' => 'string'], 'avatar_initials' => ['type' => 'string']]]]];
    }
}
