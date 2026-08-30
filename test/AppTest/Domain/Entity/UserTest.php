<?php

declare(strict_types=1);

namespace AppTest\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\Value\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private function user(): User
    {
        return User::register(
            Email::fromString('jane@example.com'),
            'Jane Doe',
            'hash',
            new DateTimeImmutable('2026-01-01'),
        );
    }

    public function testRegisterProducesUnsavedUser(): void
    {
        $user = $this->user();

        self::assertNull($user->id);
        self::assertSame('jane@example.com', (string) $user->email);
    }

    public function testMutatorsAreImmutable(): void
    {
        $user = $this->user();

        $renamed = $user->rename('Jane Smith')->withId(5)->withPasswordHash('newhash');

        self::assertSame('Jane Smith', $renamed->displayName);
        self::assertSame(5, $renamed->id);
        self::assertSame('newhash', $renamed->passwordHash);

        self::assertSame('Jane Doe', $user->displayName);
        self::assertNull($user->id);
    }
}
