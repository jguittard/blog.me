<?php

declare(strict_types=1);

namespace AdminTest\CurrentUser;

use Admin\CurrentUser\CurrentUserProvider;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CurrentUserProviderTest extends TestCase
{
    public function testReturnsAndCachesTheConfiguredUser(): void
    {
        $user = User::register(Email::fromString('julien@guittard.me'), 'Julien', 'hash');

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())
            ->method('findByEmail')
            ->with(self::callback(static fn (Email $e): bool => (string) $e === 'julien@guittard.me'))
            ->willReturn($user);

        $provider = new CurrentUserProvider($users, 'julien@guittard.me');

        self::assertSame($user, $provider->get());
        self::assertSame($user, $provider->get()); // cached, findByEmail still once
    }

    public function testThrowsWhenTheUserIsMissing(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);

        $this->expectException(RuntimeException::class);

        (new CurrentUserProvider($users, 'nobody@example.com'))->get();
    }
}
