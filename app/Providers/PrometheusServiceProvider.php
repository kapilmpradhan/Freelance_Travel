<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use App\Services\Prometheus\PrometheusService;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/prometheus.php'),
            'prometheus'
        );

        $this->app->singleton(PrometheusService::class, function ($app) {
            return new PrometheusService();
        });

        $this->app->alias(PrometheusService::class, 'prometheus');
    }

    public function boot(): void
    {
        if (!config('prometheus.enabled')) {
            return;
        }

        $this->registerDatabaseListeners();
        $this->registerQueueListeners();
    }

    protected function registerDatabaseListeners(): void
    {
        if (!config('prometheus.collect.database')) {
            return;
        }

        DB::listen(function ($query) {
            app(PrometheusService::class)->recordDatabaseQuery(
                $query->sql,
                $query->time,
                $query->connectionName
            );
        });
    }

    protected function registerQueueListeners(): void
    {
        if (!config('prometheus.collect.queue')) {
            return;
        }

        Queue::before(function (JobProcessing $event) {
            $event->job->startTime = microtime(true);
        });

        Queue::after(function (JobProcessed $event) {
            $duration = isset($event->job->startTime)
                ? microtime(true) - $event->job->startTime
                : 0;

            app(PrometheusService::class)->recordQueueJob(
                $event->job->resolveName(),
                $event->connectionName,
                $event->job->getQueue(),
                'completed',
                $duration
            );
        });

        Queue::failing(function (JobFailed $event) {
            $duration = isset($event->job->startTime)
                ? microtime(true) - $event->job->startTime
                : 0;

            $prometheus = app(PrometheusService::class);

            $prometheus->recordQueueJob(
                $event->job->resolveName(),
                $event->connectionName,
                $event->job->getQueue(),
                'failed',
                $duration
            );

            $prometheus->recordQueueJobFailed(
                $event->job->resolveName(),
                $event->connectionName,
                $event->job->getQueue(),
                $event->exception ? get_class($event->exception) : 'Unknown'
            );
        });
    }
}
