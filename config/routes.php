<?php

declare(strict_types=1);

use App\Handler\CategoryListHandler;
use App\Handler\CategoryPostsHandler;
use App\Handler\PingHandler;
use App\Handler\PostListHandler;
use App\Handler\PostViewHandler;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * FastRoute route configuration
 *
 * @see https://github.com/nikic/FastRoute
 */

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/', PostListHandler::class, 'blog.list');
    $app->get('/posts/{slug}', PostViewHandler::class, 'blog.post');
    $app->get('/categories', CategoryListHandler::class, 'blog.categories');
    $app->get('/categories/{slug}', CategoryPostsHandler::class, 'blog.category');

    $app->get('/api/ping', PingHandler::class, 'api.ping');
};
