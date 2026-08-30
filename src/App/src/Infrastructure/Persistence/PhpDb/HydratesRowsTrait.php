<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use Laminas\Hydrator\HydratorInterface;
use PhpDb\ResultSet\ResultSetInterface;
use PhpDb\TableGateway\TableGateway;

use function is_object;

trait HydratesRowsTrait
{
    /** Return the first hydrated row of a result set, or null. */
    private function firstOf(ResultSetInterface $resultSet): ?object
    {
        /** @var mixed $row */
        foreach ($resultSet as $row) {
            return is_object($row) ? $row : null;
        }

        return null;
    }

    /**
     * Insert a new row, or update the existing one keyed by its CUID primary
     * key. Application-generated ids mean there is no auto-increment to read
     * back, so we probe for the row first.
     */
    private function upsert(TableGateway $table, HydratorInterface $hydrator, string $id, object $entity): void
    {
        /** @var array<string, mixed> $data */
        $data = $hydrator->extract($entity);

        if ($this->firstOf($table->select(['id' => $id])) !== null) {
            unset($data['id']);
            $table->update($data, ['id' => $id]);

            return;
        }

        $table->insert($data);
    }
}
