<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\Pagination;
use PHPUnit\Framework\TestCase;

use function array_fill;

final class PaginationTest extends TestCase
{
    public function testDefaultsToPageOne(): void
    {
        $page = Pagination::fromQuery([]);

        self::assertSame(1, $page->page);
        self::assertSame(0, $page->offset());
        self::assertFalse($page->hasPrev());
    }

    public function testClampsNonPositivePages(): void
    {
        self::assertSame(1, Pagination::fromQuery(['page' => '0'])->page);
        self::assertSame(1, Pagination::fromQuery(['page' => '-5'])->page);
        self::assertSame(1, Pagination::fromQuery(['page' => 'nonsense'])->page);
    }

    public function testOffsetAndPrevForLaterPages(): void
    {
        $page = Pagination::fromQuery(['page' => '3']);

        self::assertSame(3, $page->page);
        self::assertSame(20, $page->offset());
        self::assertTrue($page->hasPrev());
    }

    public function testFetchLimitIsOneMoreThanPageSize(): void
    {
        self::assertSame(Pagination::PER_PAGE + 1, Pagination::fromQuery([])->fetchLimit());
    }

    public function testHasNextAndPageItemsTrimTheProbeRow(): void
    {
        $page = Pagination::fromQuery([]);

        $full = array_fill(0, Pagination::PER_PAGE + 1, 'x');
        self::assertTrue($page->hasNext($full));
        self::assertCount(Pagination::PER_PAGE, $page->pageItems($full));

        $short = array_fill(0, 4, 'x');
        self::assertFalse($page->hasNext($short));
        self::assertCount(4, $page->pageItems($short));
    }
}
