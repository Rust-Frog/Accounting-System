<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;

/**
 * Health check controller for system monitoring.
 */
final class HealthController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?\Predis\Client $redis = null
    ) {
    }

    public function check(): ResponseInterface
    {
        $status = [
            'status' => 'up',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkRedis(),
            ]
        ];

        $statusCode = 200;
        foreach ($status['services'] as $service) {
            if ($service['status'] !== 'ok') {
                $status['status'] = 'degraded';
            }
        }

        return JsonResponse::success($status, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            $this->pdo->query('SELECT 1');
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed'
            ];
        }
    }

    private function checkRedis(): array
    {
        if ($this->redis === null) {
            return ['status' => 'disabled'];
        }

        try {
            $this->redis->ping();
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Redis connection failed'
            ];
        }
    }
}
