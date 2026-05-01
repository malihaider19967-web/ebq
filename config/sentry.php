<?php

return [

    // Pull from .env. Empty DSN disables Sentry — useful for local
    // development so devs aren't paying into the production org.
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Application's Git commit SHA / build version. The release-checker
    // package can autodetect this; set explicitly in CI for reliable
    // source-map / commit attribution.
    'release' => env('SENTRY_RELEASE'),

    // Logical environment (production / staging / local). Defaults to
    // Laravel's APP_ENV so issues are filterable by stage in Sentry.
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Sample rate for non-error events (0.0–1.0). 1.0 = capture every
    // exception. Lower if you ever hit Sentry's quota.
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    // Performance (transaction) sampling — separate from error sampling.
    // 0.0 disables performance monitoring entirely; raise per-environment
    // if you want trace data. Default disabled so we don't ship perf
    // overhead until explicitly opted-in.
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    // Profiling sampling (server-side PHP profiler) — applies on top of
    // traces_sample_rate. Same opt-in pattern as traces.
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // PII scrubbing — when false, Sentry strips IPs/emails/usernames
    // from events before they leave the server. Set to true only if
    // you've reviewed PII handling against your privacy policy and
    // GDPR obligations. Our policy commits to scrubbing, so default=false.
    'send_default_pii' => (bool) env('SENTRY_SEND_DEFAULT_PII', false),

    // Stack-trace capture for log-channel events ("logger.error" etc.).
    'attach_stacktrace' => (bool) env('SENTRY_ATTACH_STACKTRACE', true),

    // Capture-context controls — useful to keep events lean. Strings
    // like a 50KB POST body would otherwise inflate the event payload.
    'max_request_body_size' => env('SENTRY_MAX_REQUEST_BODY_SIZE', 'medium'),

    // Breadcrumbs — Sentry's app-side timeline before each error.
    'breadcrumbs' => [
        // Capture Laravel log() calls.
        'logs' => true,
        // Capture cache hits/misses (can be noisy on high-traffic pages).
        'cache' => false,
        // Capture every executed query (off by default — too noisy).
        'sql_queries' => false,
        // Capture only the slow / explicit queries via the bindings flag.
        'sql_bindings' => false,
        // Capture queue job lifecycle (dispatch / handle / fail).
        'queue_info' => true,
        // Capture HTTP client outbound requests.
        'http_client_requests' => true,
        // Capture livewire component lifecycle events.
        'livewire' => true,
        // Capture command starts so we know which job context errored.
        'command_info' => true,
    ],

    // Tracing — what types of operations to track when traces_sample_rate > 0.
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'sql_origin_threshold_ms' => 100,
        'views' => true,
        'livewire' => true,
        'http_client_requests' => true,
        'redis_commands' => true,
        'cache' => false,
        'missing_routes' => false,
    ],

    // List of integrations to disable — usually empty.
    'integrations' => [],

    // Exception classes that should NEVER be reported. The HTTP exception
    // for 404s is excluded by Laravel's exception handler too, but pinning
    // it here protects against double-reporting if reporting is wired up
    // anywhere we forgot.
    'ignore_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
    ],

    // Transactions matching these names are dropped. Add health-check
    // and known-noisy paths to keep the dashboard signal-to-noise high.
    'ignore_transactions' => [
        '/up',
        'GET /up',
    ],
];
