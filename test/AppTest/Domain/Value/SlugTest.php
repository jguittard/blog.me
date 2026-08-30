<?php

declare(strict_types=1);

namespace AppTest\Domain\Value;

use App\Domain\Value\Slug;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testFromStringAcceptsValidSlug(): void
    {
        $slug = Slug::fromString('hello-world-8-5');

        self::assertSame('hello-world-8-5', $slug->value);
        self::assertSame('hello-world-8-5', (string) $slug);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSlugs(): iterable
    {
        yield 'spaces'          => ['hello world'];
        yield 'uppercase'       => ['Hello'];
        yield 'leading hyphen'  => ['-hello'];
        yield 'trailing hyphen' => ['hello-'];
        yield 'double hyphen'   => ['hello--world'];
        yield 'empty'           => [''];
    }

    #[DataProvider('invalidSlugs')]
    public function testFromStringRejectsInvalidSlug(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Slug::fromString($value);
    }

    public function testFromTitleDerivesSlug(): void
    {
        self::assertSame(
            'property-hooks-in-php-8-5',
            Slug::fromTitle('  Property Hooks in PHP 8.5!  ')->value,
        );
    }

    public function testFromTitleRejectsUnsluggableInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Slug::fromTitle('!!!');
    }
}
