<?php

declare(strict_types=1);

use PhpDb\Adapter\AdapterInterface;

// MySQL/MariaDB adapter for php-db/phpdb (+ php-db/phpdb-mysql).
// Connection details come from DATABASE_URL (mysql://user:pass@host:port/dbname);
// the fallback matches the Docker `mariadb` service. Override per-environment
// from a *.local.php file.

$url = (array) parse_url(getenv('DATABASE_URL') ?: 'mysql://blog:blog@mariadb:3306/blog');

return [
    AdapterInterface::class => [
        'driver'     => 'Pdo_Mysql',
        'connection' => [
            'host'     => $url['host'] ?? 'mariadb',
            'port'     => $url['port'] ?? 3306,
            'dbname'   => isset($url['path']) ? ltrim($url['path'], '/') : 'blog',
            'username' => $url['user'] ?? 'blog',
            'password' => $url['pass'] ?? 'blog',
            'charset'  => 'utf8mb4',
        ],
    ],
];
