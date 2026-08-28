<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\ErrorHandler\Internal\ResolvedRoute;

/**
 * PSR-15 middleware that names the route a request matched, so a reported error carries the
 * endpoint instead of the raw path.
 *
 * <p>An {@see ErrorMiddleware} sits above the router, because that is the only place a routing
 * exception can be caught, and by then the request it holds is the one from before the match. This
 * middleware sits below the router, where the match is known, and writes the name into the carrier
 * the error middleware left on the request. Register it inside the routing middleware, so the
 * routing exceptions keep reaching the error middleware.</p>
 *
 * <p>The resolver returns the pattern and never the path. A path carries identifiers, so it names a
 * request rather than an endpoint, and every provider that indexes this value penalizes the
 * cardinality that follows.</p>
 */
final readonly class RouteMiddleware implements MiddlewareInterface
{
    /** @param Closure(ServerRequestInterface): ?string $resolver */
    private function __construct(private Closure $resolver)
    {
    }

    /**
     * Creates a RouteMiddleware from the closure that names the matched route.
     *
     * <p>The closure reads the framework the consumer routes with, which is why the library asks
     * for it instead of guessing. It returns <code>null</code> when no route matched.</p>
     *
     * @param Closure(ServerRequestInterface): ?string $resolver The closure naming the matched route.
     * @return RouteMiddleware The configured middleware.
     */
    public static function resolvedBy(Closure $resolver): RouteMiddleware
    {
        return new RouteMiddleware(resolver: $resolver);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ResolvedRoute::from(request: $request)?->resolvedTo(pattern: ($this->resolver)($request));

        return $handler->handle($request);
    }
}
