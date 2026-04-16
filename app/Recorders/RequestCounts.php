<?php

namespace App\Recorders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns;
use Symfony\Component\HttpFoundation\Response;

class RequestCounts
{
    use Concerns\Ignores,
        Concerns\LivewireRoutes,
        Concerns\Sampling,
        ConfiguresAfterResolving;

    public function __construct(
        protected Pulse $pulse,
    ) {}

    public function register(callable $record, Application $app): void
    {
        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record) // @phpstan-ignore method.notFound
        );
    }

    public function record(Carbon $startedAt, Request $request, Response $response): void
    {
        if (! $request->route() instanceof Route || ! $this->shouldSample()) {
            return;
        }

        [$path, $via] = $this->resolveRoutePath($request);

        if ($this->shouldIgnore($path)) {
            return;
        }

        $this->pulse->record(
            type: 'request_count',
            key: json_encode([$request->method(), $path, $via], flags: JSON_THROW_ON_ERROR),
            timestamp: $startedAt,
        )->count();
    }
}
