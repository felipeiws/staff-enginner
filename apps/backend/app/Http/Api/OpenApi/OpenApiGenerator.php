<?php

declare(strict_types=1);

namespace App\Http\Api\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;

final class OpenApiGenerator
{
    public function __construct(private readonly Router $router) {}

    /** @return array<string, mixed> */
    public function generate(): array
    {
        $paths = [];
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || $route->uri() === 'api/v1/openapi.json') {
                continue;
            }
            $operation = $this->operation($route);
            if ($operation === null) {
                continue;
            }
            $path = '/'.preg_replace('/\{([^}]+)\}/', '{$1}', $route->uri());
            foreach (array_filter($route->methods(), static fn (string $method): bool => ! in_array($method, ['HEAD', 'OPTIONS'], true)) as $method) {
                $responses = [];
                foreach ($operation->responses as $status => $description) {
                    $responses[(string) $status] = ['description' => $description];
                }
                $responses['default'] = ['$ref' => '#/components/responses/Problem'];
                $paths[$path][strtolower($method)] = ['summary' => $operation->summary, 'tags' => $operation->tags, 'responses' => $responses];
            }
        }
        ksort($paths);

        return [
            'openapi' => '3.1.0', 'jsonSchemaDialect' => 'https://spec.openapis.org/oas/3.1/dialect/base',
            'info' => ['title' => 'Atlas API', 'version' => 'v1', 'description' => 'Borda HTTP versionada da Atlas Knowledge Platform.'],
            'paths' => $paths,
            'components' => ['responses' => ['Problem' => ['description' => 'Erro RFC 9457', 'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]]], 'schemas' => ['Problem' => ['type' => 'object', 'required' => ['type', 'title', 'status', 'detail', 'instance', 'code', 'correlation_id'], 'properties' => ['type' => ['type' => 'string', 'format' => 'uri'], 'title' => ['type' => 'string'], 'status' => ['type' => 'integer'], 'detail' => ['type' => 'string'], 'instance' => ['type' => 'string'], 'code' => ['type' => 'string'], 'correlation_id' => ['type' => 'string']]]]],
        ];
    }

    private function operation(Route $route): ?ApiOperation
    {
        $action = $route->getAction('controller');
        if (! is_string($action)) {
            return null;
        }
        [$class, $method] = str_contains($action, '@') ? explode('@', $action, 2) : [$action, '__invoke'];
        if (! class_exists($class)) {
            return null;
        }
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(ApiOperation::class);
        if ($attributes === []) {
            $attributes = (new ReflectionMethod($class, $method))->getAttributes(ApiOperation::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
