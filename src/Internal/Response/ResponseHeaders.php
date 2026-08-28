<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

final readonly class ResponseHeaders
{
    private const string NAME = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';
    private const string VALUE = '/^[\x20\x09\x21-\x7E\x80-\xFF]*$/';

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
        return preg_match(self::NAME, $name) === 1
            && array_all(
                $values,
                static fn(mixed $value): bool => is_scalar($value) && preg_match(self::VALUE, (string)$value) === 1
            );
    }
}
