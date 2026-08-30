<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Value\Email;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use Laminas\Hydrator\HydratorInterface;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\ResultSet\HydratingResultSet;
use PhpDb\TableGateway\Feature\FeatureSet;
use PhpDb\TableGateway\TableGateway;
use ReflectionClass;

/**
 * @psalm-api Instantiated by the DI container / migrate CLI.
 */
final class PhpDbUserRepository implements UserRepositoryInterface
{
    use HydratesRowsTrait;

    private readonly HydratorInterface $hydrator;
    private readonly TableGateway $table;

    public function __construct(
        private readonly AdapterInterface $db,
        HydratorRegistry $hydrators,
    ) {
        $this->hydrator = $hydrators->user();
        $prototype      = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->table    = new TableGateway(
            'users',
            $db,
            new FeatureSet(),
            new HydratingResultSet($this->hydrator, $prototype),
        );
    }

    public function find(int $id): ?User
    {
        $row = $this->firstOf($this->table->select(['id' => $id]));

        return $row instanceof User ? $row : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $row = $this->firstOf($this->table->select(['email' => (string) $email]));

        return $row instanceof User ? $row : null;
    }

    public function save(User $user): User
    {
        /** @var array<string, mixed> $data */
        $data = $this->hydrator->extract($user);
        unset($data['id']);

        if ($user->id === null) {
            $this->table->insert($data);

            return $user->withId((int) $this->db->getDriver()->getConnection()->getLastGeneratedValue());
        }

        $this->table->update($data, ['id' => $user->id]);

        return $user;
    }

    public function delete(int $id): void
    {
        $this->table->delete(['id' => $id]);
    }
}
