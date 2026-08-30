<?php

declare(strict_types=1);

namespace AppTest\Domain\Value;

use App\Domain\Value\PostStatus;
use PHPUnit\Framework\TestCase;

final class PostStatusTest extends TestCase
{
    public function testBackedValues(): void
    {
        self::assertSame('draft', PostStatus::Draft->value);
        self::assertSame('published', PostStatus::Published->value);
        self::assertSame('archived', PostStatus::Archived->value);
        self::assertSame(PostStatus::Published, PostStatus::from('published'));
    }

    public function testOnlyPublishedIsPublic(): void
    {
        self::assertTrue(PostStatus::Published->isPublic());
        self::assertFalse(PostStatus::Draft->isPublic());
        self::assertFalse(PostStatus::Archived->isPublic());
    }

    public function testLabels(): void
    {
        self::assertSame('Draft', PostStatus::Draft->label());
        self::assertSame('Published', PostStatus::Published->label());
        self::assertSame('Archived', PostStatus::Archived->label());
    }
}
