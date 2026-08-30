<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Value\Cuid;
use App\Domain\Value\Email;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $id,
        public Email $email,
        public string $displayName,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        Email $email,
        string $displayName,
        string $passwordHash,
        ?DateTimeImmutable $now = null,
    ): self {
        // Second precision only, to match MySQL DATETIME.
        $now = ($now ?? new DateTimeImmutable())->setMicrosecond(0);

        return new self(Cuid::generate(), $email, $displayName, $passwordHash, $now);
    }

    public function rename(string $displayName): self
    {
        return clone($this, ['displayName' => $displayName]);
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return clone($this, ['passwordHash' => $passwordHash]);
    }
}
