<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use App\Domain\Value\Email;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;

    public function findByEmail(Email $email): ?User;

    /** Persist a new or existing user; returns it with its id populated. */
    public function save(User $user): User;

    public function delete(string $id): void;
}
