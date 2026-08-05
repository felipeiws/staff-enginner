<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Redis\RedisManager;
use Throwable;

class HealthCheckService
{
    public function __construct(
        private DatabaseManager $database,
        private RedisManager $redis,
        private Filesystem $storage,
    ) {}

    /**
     * @return array{status: 'ok'|'degraded', checks: array{database: 'ok'|'failed', redis: 'ok'|'failed', storage: 'ok'|'failed'}}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        return [
            'status' => in_array('failed', $checks, true) ? 'degraded' : 'ok',
            'checks' => $checks,
        ];
    }

    /** @return 'ok'|'failed' */
    private function checkDatabase(): string
    {
        try {
            $this->database->connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /** @return 'ok'|'failed' */
    private function checkRedis(): string
    {
        try {
            $this->redis->connection()->ping();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /** @return 'ok'|'failed' */
    private function checkStorage(): string
    {
        try {
            $this->storage->exists('healthcheck-probe');

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
