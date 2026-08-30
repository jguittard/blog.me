<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use PhpDb\ResultSet\ResultSetInterface;

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
}
