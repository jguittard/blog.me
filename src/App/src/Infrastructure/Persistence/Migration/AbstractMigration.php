<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Sql;
use PhpDb\Sql\SqlInterface;
use ReflectionClass;

use function preg_replace;
use function substr;

abstract class AbstractMigration implements MigrationInterface
{
    /** Derive "20260101000004" from a `Version20260101000004*` class name. */
    public function version(): string
    {
        $digits = preg_replace('/\D/', '', (new ReflectionClass($this))->getShortName());

        return substr($digits ?? '', 0, 14);
    }

    /** Run a DDL statement object (or raw SQL) against the connection. */
    protected function execute(AdapterInterface $db, SqlInterface|string $statement): void
    {
        $sql = $statement instanceof SqlInterface
            ? (new Sql($db))->buildSqlString($statement)
            : $statement;

        $db->executeQuery($sql);
    }
}
