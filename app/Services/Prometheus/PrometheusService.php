<?php

namespace App\Services\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use App\Logging\Logger;

class PrometheusService
{
    private CollectorRegistry $registry;
    private string $namespace;

    private $httpRequestCounter = null;
    private $httpRequestDuration = null;
    private $databaseQueryCounter = null;
    private $databaseQueryDuration = null;
    private $queueJobCounter = null;
    private $queueJobDuration = null;
    private $queueJobFailedCounter = null;

    public function __construct()
    {
        $adapter = $this->createStorageAdapter();
        $this->registry = new CollectorRegistry($adapter);
        $this->namespace = config('prometheus.namespace', 'app');
    }

    private function createStorageAdapter()
    {
        // Check if native PHP Redis extension is available
        if (!extension_loaded('redis')) {
            Logger::info('PHP Redis extension not available, using InMemory storage for Prometheus metrics');
            return new InMemory();
        }

        $config = config('prometheus.storage');

        return new Redis([
            'host' => $config['host'],
            'port' => (int) $config['port'],
            'password' => $config['password'],
            'database' => (int) $config['database'],
        ]);
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }

    /**
     * Record HTTP request metrics
     */
    public function recordHttpRequest(
        string $method,
        string $route,
        int $statusCode,
        float $duration
    ): void {
        try {
            $this->getHttpRequestCounter()->incBy(1, [
                $method,
                $this->normalizeRoute($route),
                (string) $statusCode,
            ]);

            $this->getHttpRequestDuration()->observe($duration, [
                $method,
                $this->normalizeRoute($route),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to record HTTP metrics', $e);
        }
    }

    /**
     * Record database query metrics
     */
    public function recordDatabaseQuery(
        string $sql,
        float $timeMs,
        string $connection
    ): void {
        try {
            $queryType = $this->extractQueryType($sql);

            $this->getDatabaseQueryCounter()->incBy(1, [
                $connection,
                $queryType,
            ]);

            $this->getDatabaseQueryDuration()->observe(
                $timeMs / 1000,
                [$connection, $queryType]
            );
        } catch (\Exception $e) {
            Logger::error('Failed to record database metrics', $e);
        }
    }

    /**
     * Record queue job metrics
     */
    public function recordQueueJob(
        string $jobClass,
        string $connection,
        string $queue,
        string $status,
        float $duration
    ): void {
        try {
            $jobName = class_basename($jobClass);

            $this->getQueueJobCounter()->incBy(1, [
                $jobName,
                $connection,
                $queue,
                $status,
            ]);

            $this->getQueueJobDuration()->observe($duration, [
                $jobName,
                $connection,
                $queue,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to record queue metrics', $e);
        }
    }

    /**
     * Record failed queue job with exception details
     */
    public function recordQueueJobFailed(
        string $jobClass,
        string $connection,
        string $queue,
        string $exceptionClass
    ): void {
        try {
            $jobName = class_basename($jobClass);
            $exceptionName = class_basename($exceptionClass);

            $this->getQueueJobFailedCounter()->incBy(1, [
                $jobName,
                $connection,
                $queue,
                $exceptionName,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to record queue failed metrics', $e);
        }
    }

    /**
     * Render metrics in Prometheus format
     */
    public function render(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    private function getHttpRequestCounter()
    {
        if ($this->httpRequestCounter === null) {
            $this->httpRequestCounter = $this->registry->getOrRegisterCounter(
                $this->namespace,
                'http_requests_total',
                'Total HTTP requests',
                ['method', 'route', 'status_code']
            );
        }
        return $this->httpRequestCounter;
    }

    private function getHttpRequestDuration()
    {
        if ($this->httpRequestDuration === null) {
            $this->httpRequestDuration = $this->registry->getOrRegisterHistogram(
                $this->namespace,
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                ['method', 'route'],
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            );
        }
        return $this->httpRequestDuration;
    }

    private function getDatabaseQueryCounter()
    {
        if ($this->databaseQueryCounter === null) {
            $this->databaseQueryCounter = $this->registry->getOrRegisterCounter(
                $this->namespace,
                'database_queries_total',
                'Total database queries',
                ['connection', 'query_type']
            );
        }
        return $this->databaseQueryCounter;
    }

    private function getDatabaseQueryDuration()
    {
        if ($this->databaseQueryDuration === null) {
            $this->databaseQueryDuration = $this->registry->getOrRegisterHistogram(
                $this->namespace,
                'database_query_duration_seconds',
                'Database query duration in seconds',
                ['connection', 'query_type'],
                [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1]
            );
        }
        return $this->databaseQueryDuration;
    }

    private function getQueueJobCounter()
    {
        if ($this->queueJobCounter === null) {
            $this->queueJobCounter = $this->registry->getOrRegisterCounter(
                $this->namespace,
                'queue_jobs_total',
                'Total queue jobs processed',
                ['job', 'connection', 'queue', 'status']
            );
        }
        return $this->queueJobCounter;
    }

    private function getQueueJobDuration()
    {
        if ($this->queueJobDuration === null) {
            $this->queueJobDuration = $this->registry->getOrRegisterHistogram(
                $this->namespace,
                'queue_job_duration_seconds',
                'Queue job processing duration in seconds',
                ['job', 'connection', 'queue'],
                [0.1, 0.5, 1, 2.5, 5, 10, 30, 60, 120]
            );
        }
        return $this->queueJobDuration;
    }

    private function getQueueJobFailedCounter()
    {
        if ($this->queueJobFailedCounter === null) {
            $this->queueJobFailedCounter = $this->registry->getOrRegisterCounter(
                $this->namespace,
                'queue_jobs_failed_total',
                'Total failed queue jobs with exception details',
                ['job', 'connection', 'queue', 'exception']
            );
        }
        return $this->queueJobFailedCounter;
    }

    private function normalizeRoute(string $route): string
    {
        $patterns = config('prometheus.route_normalization', []);

        foreach ($patterns as $pattern => $replacement) {
            $route = preg_replace($pattern, $replacement, $route);
        }

        return $route ?: '/';
    }

    private function extractQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (str_starts_with($sql, 'SELECT')) {
            return 'select';
        }
        if (str_starts_with($sql, 'INSERT')) {
            return 'insert';
        }
        if (str_starts_with($sql, 'UPDATE')) {
            return 'update';
        }
        if (str_starts_with($sql, 'DELETE')) {
            return 'delete';
        }
        if (str_starts_with($sql, 'BEGIN')) {
            return 'transaction';
        }
        if (str_starts_with($sql, 'COMMIT')) {
            return 'transaction';
        }
        if (str_starts_with($sql, 'ROLLBACK')) {
            return 'transaction';
        }

        return 'other';
    }
}
