<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Migration\MigrationRunner;
use Psr\Container\ContainerInterface;

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require 'config/container.php';
$runner    = $container->get(MigrationRunner::class);

$command = $argv[1] ?? 'migrate';

switch ($command) {
    case 'migrate':
    case 'up':
        $applied = $runner->migrate();
        echo $applied === []
            ? "Nothing to migrate; schema is up to date." . PHP_EOL
            : "Applied: " . implode(', ', $applied) . PHP_EOL;
        break;

    case 'rollback':
    case 'down':
        $steps    = isset($argv[2]) ? (int) $argv[2] : 1;
        $reverted = $runner->rollback($steps);
        echo $reverted === []
            ? "Nothing to roll back." . PHP_EOL
            : "Reverted: " . implode(', ', $reverted) . PHP_EOL;
        break;

    case 'status':
        foreach ($runner->status() as $version => $info) {
            printf("[%s] %s  %s%s", $info['applied'] ? 'x' : ' ', $version, $info['description'], PHP_EOL);
        }
        break;

    default:
        echo "Usage: php bin/migrate.php [migrate|rollback [steps]|status]" . PHP_EOL;
        exit(1);
}

exit(0);
