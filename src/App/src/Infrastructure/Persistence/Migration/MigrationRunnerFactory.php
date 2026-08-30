<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;

use function assert;

final class MigrationRunnerFactory
{
    /**
     * Registered, ordered list of migration service names.
     *
     * @var list<class-string<MigrationInterface>>
     */
    public const array MIGRATIONS = [
        Version20260101000001CreateUsersTable::class,
        Version20260101000002CreateCategoriesTable::class,
        Version20260101000003CreateTagsTable::class,
        Version20260101000004CreatePostsTable::class,
        Version20260101000005CreatePostTagTable::class,
    ];

    public function __invoke(ContainerInterface $container): MigrationRunner
    {
        $migrations = [];
        foreach (self::MIGRATIONS as $class) {
            $migration = $container->get($class);
            assert($migration instanceof MigrationInterface);
            $migrations[] = $migration;
        }

        $db = $container->get(AdapterInterface::class);
        assert($db instanceof AdapterInterface);

        return new MigrationRunner($db, $migrations);
    }
}
