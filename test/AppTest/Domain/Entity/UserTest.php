<?php

declare(strict_types=1);

namespace AppTest\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\Value\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function preg_match;

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

    public function testRegisterGeneratesACuidAndNormalisesEmail(): void
    {
        $user = $this->user();

        self::assertSame(1, preg_match('/^[a-z0-9]{24}$/', $user->id));
        self::assertSame('jane@example.com', (string) $user->email);
    }

    public function testMutatorsAreImmutableAndKeepTheId(): void
    {
        $user    = $this->user();
        $renamed = $user->rename('Jane Smith')->withPasswordHash('newhash');

        self::assertSame('Jane Smith', $renamed->displayName);
        self::assertSame('newhash', $renamed->passwordHash);
        self::assertSame($user->id, $renamed->id);

        self::assertSame('Jane Doe', $user->displayName);
        self::assertSame('hash', $user->passwordHash);
    }
}
