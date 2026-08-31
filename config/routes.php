<?php

declare(strict_types=1);

use Admin\Handler\CategoryCreateHandler as AdminCategoryCreateHandler;
use Admin\Handler\CategoryDeleteHandler as AdminCategoryDeleteHandler;
use Admin\Handler\CategoryEditHandler as AdminCategoryEditHandler;
use Admin\Handler\CategoryIndexHandler as AdminCategoryIndexHandler;
use Admin\Handler\DashboardHandler as AdminDashboardHandler;
use Admin\Handler\PostCreateHandler as AdminPostCreateHandler;
use Admin\Handler\PostDeleteHandler as AdminPostDeleteHandler;
use Admin\Handler\PostEditHandler as AdminPostEditHandler;
use Admin\Handler\PostIndexHandler as AdminPostIndexHandler;
use Admin\Handler\TagCreateHandler as AdminTagCreateHandler;
use Admin\Handler\TagDeleteHandler as AdminTagDeleteHandler;
use Admin\Handler\TagEditHandler as AdminTagEditHandler;
use Admin\Handler\TagIndexHandler as AdminTagIndexHandler;
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

    // --- Admin (no auth yet; current user is hard-coded) ---
    $app->get('/admin', AdminDashboardHandler::class, 'admin.dashboard');

    $app->get('/admin/posts', AdminPostIndexHandler::class, 'admin.posts.index');
    $app->route('/admin/posts/new', AdminPostCreateHandler::class, ['GET', 'POST'], 'admin.posts.create');
    $app->route('/admin/posts/{id}/edit', AdminPostEditHandler::class, ['GET', 'POST'], 'admin.posts.edit');
    $app->post('/admin/posts/{id}/delete', AdminPostDeleteHandler::class, 'admin.posts.delete');

    $app->get('/admin/categories', AdminCategoryIndexHandler::class, 'admin.categories.index');
    $app->route('/admin/categories/new', AdminCategoryCreateHandler::class, ['GET', 'POST'], 'admin.categories.create');
    $app->route('/admin/categories/{id}/edit', AdminCategoryEditHandler::class, ['GET', 'POST'], 'admin.categories.edit');
    $app->post('/admin/categories/{id}/delete', AdminCategoryDeleteHandler::class, 'admin.categories.delete');

    $app->get('/admin/tags', AdminTagIndexHandler::class, 'admin.tags.index');
    $app->route('/admin/tags/new', AdminTagCreateHandler::class, ['GET', 'POST'], 'admin.tags.create');
    $app->route('/admin/tags/{id}/edit', AdminTagEditHandler::class, ['GET', 'POST'], 'admin.tags.edit');
    $app->post('/admin/tags/{id}/delete', AdminTagDeleteHandler::class, 'admin.tags.delete');
};
