<?php

declare(strict_types=1);

namespace Admin\CurrentUser;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use RuntimeException;

/**
 * Stand-in for real authentication: resolves a single, configured user that the
 * admin area treats as "logged in" (post author + header display). Replace with
 * a session-backed identity when auth lands.
 */
final class CurrentUserProvider
{
    private ?User $cached = null;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly string $email,
    ) {
    }

    public function get(): User
    {
        return $this->cached ??= $this->users->findByEmail(Email::fromString($this->email))
            ?? throw new RuntimeException(
                "Admin user <{$this->email}> not found. Run `make seed` or set admin.current_user_email.",
            );
    }
}
