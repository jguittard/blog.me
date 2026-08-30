<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Hydrator;

use Closure;
use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * Maps a scalar column to a single-value object and back.
 *
 * Hydrate: `$factory($columnValue)`. Extract: `(string) $valueObject`.
 */
final class ValueObjectStrategy implements StrategyInterface
{
    /** @param Closure(string): object $factory */
    public function __construct(private readonly Closure $factory)
    {
    }

    /** @param mixed $value */
    public function extract($value, ?object $object = null): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  mixed $value
     * @param  array<array-key, mixed>|null $data
     * @return object|null
     */
    public function hydrate($value, ?array $data = null)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($this->factory)((string) $value);
    }
}
