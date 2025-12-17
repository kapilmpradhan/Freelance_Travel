## Prometheus Metrics

This application includes built-in Prometheus metrics for monitoring. Metrics are exposed at `/api/metrics`.

### Configuration

Add the following to your `.env` file:

```env
# Enable/Disable Prometheus entirely
PROMETHEUS_ENABLED=true

# Metrics namespace (prefix for all metrics)
PROMETHEUS_NAMESPACE=travel_app

# Redis database for storing metrics (separate from cache)
PROMETHEUS_REDIS_DB=2

# Redis key prefix
PROMETHEUS_PREFIX=app_metrics_
```

### Enable/Disable Specific Collectors

Edit `config/prometheus.php` to toggle individual metric collectors:

```php
'collect' => [
    'http' => true,       // HTTP request metrics (count, duration, status codes)
    'database' => true,   // Database query metrics (count, duration by type)
    'queue' => true,      // Queue job metrics (count, duration, success/failure)
    'business' => true,   // Custom business metrics (orders, registrations, etc.)
],
```

| Collector | Metrics | Description |
|-----------|---------|-------------|
| `http` | `http_requests_total`, `http_request_duration_seconds` | Tracks all HTTP requests |
| `database` | `database_queries_total`, `database_query_duration_seconds` | Tracks all DB queries |
| `queue` | `queue_jobs_total`, `queue_jobs_failed_total`, `queue_job_duration_seconds` | Tracks background job execution |
| `business` | Custom metrics | Application-specific metrics |

### Available Metrics

| Metric Name | Type | Labels | Description |
|-------------|------|--------|-------------|
| `travel_app_http_requests_total` | Counter | method, route, status_code | Total HTTP requests |
| `travel_app_http_request_duration_seconds` | Histogram | method, route | Request duration |
| `travel_app_database_queries_total` | Counter | connection, query_type | Total DB queries |
| `travel_app_database_query_duration_seconds` | Histogram | connection, query_type | Query duration |
| `travel_app_queue_jobs_total` | Counter | job, connection, queue, status | Total queue jobs |
| `travel_app_queue_jobs_failed_total` | Counter | job, connection, queue, exception | Failed jobs with exception type |
| `travel_app_queue_job_duration_seconds` | Histogram | job, connection, queue | Job duration |

### Adding Custom Business Metrics

Use the `RecordsBusinessMetrics` trait in any service or controller:

```php
use App\Services\Prometheus\Traits\RecordsBusinessMetrics;

class YourService
{
    use RecordsBusinessMetrics;

    public function someMethod()
    {
        // Record a counter (increments)
        $this->recordBusinessMetric('your_metric_name', 1, [
            'label1' => 'value1',
            'label2' => 'value2',
        ]);

        // Record a gauge (can go up or down)
        $this->recordBusinessGauge('active_sessions', $count, [
            'type' => 'web',
        ]);
    }
}
```