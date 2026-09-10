<?php

namespace Iankibet\Streamline\Features\Streamline;

use Closure;
use Iankibet\Streamline\Features\Support\StreamlineSupport;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

/**
 * Per-request auth gate for Streamline.
 *
 * The controller's static middleware() list is memoised on the Route, so it
 * cannot decide guest-vs-auth per request (the first request through a
 * long-lived worker would fix the gate for every later request). This
 * middleware is a constant in that list and makes the decision on every
 * request instead: guest streams pass straight through, everything else is
 * sent through the configured auth middleware (`streamline.middleware`).
 */
class StreamlineGate
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->isGuest($request)) {
            return $next($request);
        }

        $middleware = config('streamline.middleware', []);

        if ($middleware === []) {
            return $next($request);
        }

        // Expand aliases / groups (e.g. `auth:sanctum`) the way the Router would.
        $resolved = app('router')->resolveMiddleware($middleware);

        return (new Pipeline(app()))
            ->send($request)
            ->through($resolved)
            ->then(fn (Request $request) => $next($request));
    }

    protected function isGuest(Request $request): bool
    {
        $stream = $this->resolveStream($request);

        if ($stream === null) {
            return false;
        }

        if (in_array($stream, config('streamline.guest_streams', []), true)) {
            return true;
        }

        return in_array(
            StreamlineSupport::convertStreamToClass($stream),
            config('streamline.guest_classes', []),
            true
        );
    }

    /**
     * The body form carries `stream` directly; the flat route carries it as
     * path segments, so rebuild it up to the first segment that resolves to a
     * Stream class.
     */
    protected function resolveStream(Request $request): ?string
    {
        $stream = $request->input('stream');

        if (is_string($stream) && $stream !== '') {
            return $stream;
        }

        $segments = array_values(array_filter(
            $request->route()?->parameters() ?? [],
            fn ($value) => is_string($value) && $value !== ''
        ));

        $path = [];

        foreach ($segments as $segment) {
            $path[] = $segment;
            $candidate = implode('/', $path);

            if (class_exists(StreamlineSupport::convertStreamToClass($candidate))) {
                return $candidate;
            }
        }

        return null;
    }
}
