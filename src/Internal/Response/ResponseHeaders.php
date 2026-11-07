<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

final readonly class ResponseHeaders
{
    private const string TE = 'te';
    private const string NAME = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';
    private const string VALUE = '/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D';
    private const string TRAILERS = 'trailers';
    private const array CONNECTION_SPECIFIC = [
        'upgrade',
        'connection',
        'keep-alive',
        'proxy-connection',
        'transfer-encoding'
    ];

    public static function malformedIn(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            $header = (string)$name;

            if (!ResponseHeaders::isWellFormed(name: $header, values: (array)$value)) {
                return $header;
            }
        }

        return null;
    }

    private static function isWellFormed(string $name, array $values): bool
    {
        $loweredName = strtolower($name);

        return preg_match(self::NAME, $name) === 1
            && !in_array($loweredName, self::CONNECTION_SPECIFIC, true)
            && array_all(
                $values,
                static fn(mixed $value): bool => is_scalar($value)
                    && preg_match(self::VALUE, (string)$value) === 1
                    && ($loweredName !== self::TE || strtolower(trim((string)$value)) === self::TRAILERS)
            );
    }
}
