<?php

namespace App\Services\Prometheus\Traits;

use App\Services\Prometheus\PrometheusService;
use App\Logging\Logger;

trait RecordsBusinessMetrics
{
    protected function recordBusinessMetric(
        string $metricName,
        float $value = 1,
        array $labels = []
    ): void {
        if (!config('prometheus.enabled') || !config('prometheus.collect.business')) {
            return;
        }

        try {
            $prometheus = app(PrometheusService::class);
            $registry = $prometheus->getRegistry();
            $namespace = config('prometheus.namespace');

            $counter = $registry->getOrRegisterCounter(
                $namespace,
                $metricName,
                "Business metric: {$metricName}",
                array_keys($labels)
            );

            $counter->incBy($value, array_values($labels));
        } catch (\Exception $e) {
            Logger::error("Failed to record business metric: {$metricName}", $e);
        }
    }

    protected function recordBusinessGauge(
        string $metricName,
        float $value,
        array $labels = []
    ): void {
        if (!config('prometheus.enabled') || !config('prometheus.collect.business')) {
            return;
        }

        try {
            $prometheus = app(PrometheusService::class);
            $registry = $prometheus->getRegistry();
            $namespace = config('prometheus.namespace');

            $gauge = $registry->getOrRegisterGauge(
                $namespace,
                $metricName,
                "Business gauge: {$metricName}",
                array_keys($labels)
            );

            $gauge->set($value, array_values($labels));
        } catch (\Exception $e) {
            Logger::error("Failed to record business gauge: {$metricName}", $e);
        }
    }
}
