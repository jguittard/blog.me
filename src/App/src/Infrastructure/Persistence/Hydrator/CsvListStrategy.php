<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Hydrator;

use Laminas\Hydrator\Strategy\StrategyInterface;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function implode;
use function is_array;

/**
 * A `GROUP_CONCAT` column (`a,b,c`, or NULL when empty) <-> `list<string>`.
 */
final class CsvListStrategy implements StrategyInterface
{
    /** @param non-empty-string $delimiter */
    public function __construct(private readonly string $delimiter = ',')
    {
    }

    /** @param mixed $value */
    public function extract($value, ?object $object = null): string
    {
        if (! is_array($value)) {
            return '';
        }

        return implode($this->delimiter, array_map(static fn (mixed $v): string => (string) $v, $value));
    }

    /**
     * @param  mixed $value
     * @param  array<array-key, mixed>|null $data
     * @return list<string>
     */
    public function hydrate($value, ?array $data = null): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parts = explode($this->delimiter, (string) $value);

        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
    }
}
