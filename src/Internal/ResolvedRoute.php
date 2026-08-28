<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ServerRequestInterface;

final class ResolvedRoute
{
    public const string ATTRIBUTE_NAME = 'tiny-blocks.error-handler.route';

    private ?string $pattern = null;

    public static function from(ServerRequestInterface $request): ?ResolvedRoute
    {
        $route = $request->getAttribute(ResolvedRoute::ATTRIBUTE_NAME);

        return $route instanceof ResolvedRoute ? $route : null;
    }

    public static function pending(): ResolvedRoute
    {
        return new ResolvedRoute();
    }

    public function pattern(): ?string
    {
        return $this->pattern;
    }

    public function resolvedTo(?string $pattern): void
    {
        $this->pattern = $pattern;
    }
}
