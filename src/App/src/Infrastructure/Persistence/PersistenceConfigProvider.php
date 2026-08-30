<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use App\Infrastructure\Persistence\Migration\MigrationRunner;
use App\Infrastructure\Persistence\Migration\MigrationRunnerFactory;
use App\Infrastructure\Persistence\PhpDb\PhpDbCategoryRepository;
use App\Infrastructure\Persistence\PhpDb\PhpDbPostReadRepository;
use App\Infrastructure\Persistence\PhpDb\PhpDbPostRepository;
use App\Infrastructure\Persistence\PhpDb\PhpDbTagRepository;
use App\Infrastructure\Persistence\PhpDb\PhpDbUserRepository;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

/**
 * Container wiring for the blog persistence layer. Registered from
 * config/config.php alongside App\ConfigProvider.
 */
final class PersistenceConfigProvider
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return ['dependencies' => $this->getDependencies()];
    }

    /** @return array<string, mixed> */
    public function getDependencies(): array
    {
        $invokables = [
            HydratorRegistry::class,
            ...MigrationRunnerFactory::MIGRATIONS,
        ];

        return [
            'aliases'    => [
                UserRepositoryInterface::class     => PhpDbUserRepository::class,
                CategoryRepositoryInterface::class => PhpDbCategoryRepository::class,
                TagRepositoryInterface::class      => PhpDbTagRepository::class,
                PostRepositoryInterface::class     => PhpDbPostRepository::class,
                PostReadRepositoryInterface::class => PhpDbPostReadRepository::class,
            ],
            'invokables' => $invokables,
            'factories'  => [
                PhpDbUserRepository::class     => ReflectionBasedAbstractFactory::class,
                PhpDbCategoryRepository::class => ReflectionBasedAbstractFactory::class,
                PhpDbTagRepository::class      => ReflectionBasedAbstractFactory::class,
                PhpDbPostRepository::class     => ReflectionBasedAbstractFactory::class,
                PhpDbPostReadRepository::class => ReflectionBasedAbstractFactory::class,
                MigrationRunner::class         => MigrationRunnerFactory::class,
            ],
        ];
    }
}
