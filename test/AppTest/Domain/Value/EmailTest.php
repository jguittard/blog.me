<?php

declare(strict_types=1);

namespace AppTest\Domain\Value;

use App\Domain\Value\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testNormalisesToLowercase(): void
    {
        $email = Email::fromString('  Jane.Doe@Example.COM ');

        self::assertSame('jane.doe@example.com', (string) $email);
    }

    public function testRejectsInvalidAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Email::fromString('not-an-email');
    }
}
