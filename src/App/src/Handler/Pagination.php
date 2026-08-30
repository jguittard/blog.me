<?php

declare(strict_types=1);

namespace App\Handler;

use function array_slice;
use function count;
use function is_scalar;
use function max;

/**
 * Tiny offset pagination for the listing handlers. Fetch `fetchLimit()` rows,
 * then `hasNext()` tells you whether a further page exists and `pageItems()`
 * trims the extra row.
 */
final readonly class Pagination
{
    public const int PER_PAGE = 10;

    private function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    /** @param array<array-key, mixed> $query typically `$request->getQueryParams()` */
    public static function fromQuery(array $query, int $perPage = self::PER_PAGE): self
    {
        /** @var mixed $raw */
        $raw = $query['page'] ?? 1;

        return new self(max(1, (int) (is_scalar($raw) ? $raw : 1)), $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function fetchLimit(): int
    {
        return $this->perPage + 1;
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    /**
     * @param  list<T> $fetched
     * @return list<T>
     * @template T
     */
    public function pageItems(array $fetched): array
    {
        return array_slice($fetched, 0, $this->perPage);
    }

    /** @param list<mixed> $fetched */
    public function hasNext(array $fetched): bool
    {
        return count($fetched) > $this->perPage;
    }
}
