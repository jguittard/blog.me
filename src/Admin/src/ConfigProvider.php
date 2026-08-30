<?php

declare(strict_types=1);

namespace Admin;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\CurrentUser\CurrentUserProviderFactory;
use Admin\Form\CategoryForm;
use Admin\Form\PostForm;
use Admin\Form\PostFormFactory;
use Admin\Form\TagForm;
use Admin\Support\PostFormMapper;
use Admin\Support\PostWriter;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

final class ConfigProvider
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'admin'        => [
                // Stand-in for authentication until it is built.
                'current_user_email' => 'julien@guittard.me',
            ],
            'dependencies' => $this->dependencies(),
            'templates'    => [
                'paths' => ['admin' => [__DIR__ . '/../templates']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function dependencies(): array
    {
        return [
            'invokables' => [
                CategoryForm::class    => CategoryForm::class,
                TagForm::class         => TagForm::class,
                PostFormMapper::class  => PostFormMapper::class,
            ],
            'factories'  => [
                CurrentUserProvider::class => CurrentUserProviderFactory::class,
                PostForm::class            => PostFormFactory::class,
                PostWriter::class          => ReflectionBasedAbstractFactory::class,
                Handler\DashboardHandler::class      => ReflectionBasedAbstractFactory::class,
                Handler\PostIndexHandler::class      => ReflectionBasedAbstractFactory::class,
                Handler\PostCreateHandler::class     => ReflectionBasedAbstractFactory::class,
                Handler\PostEditHandler::class       => ReflectionBasedAbstractFactory::class,
                Handler\PostDeleteHandler::class     => ReflectionBasedAbstractFactory::class,
                Handler\CategoryIndexHandler::class  => ReflectionBasedAbstractFactory::class,
                Handler\CategoryCreateHandler::class => ReflectionBasedAbstractFactory::class,
                Handler\CategoryEditHandler::class   => ReflectionBasedAbstractFactory::class,
                Handler\CategoryDeleteHandler::class => ReflectionBasedAbstractFactory::class,
                Handler\TagIndexHandler::class       => ReflectionBasedAbstractFactory::class,
                Handler\TagCreateHandler::class      => ReflectionBasedAbstractFactory::class,
                Handler\TagEditHandler::class        => ReflectionBasedAbstractFactory::class,
                Handler\TagDeleteHandler::class      => ReflectionBasedAbstractFactory::class,
            ],
        ];
    }
}
