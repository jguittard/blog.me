<?php

declare(strict_types=1);

namespace Admin\Form;

use App\Domain\Repository\CategoryRepositoryInterface;
use Psr\Container\ContainerInterface;

use function assert;

final class PostFormFactory
{
    public function __invoke(ContainerInterface $container): PostForm
    {
        $categories = $container->get(CategoryRepositoryInterface::class);
        assert($categories instanceof CategoryRepositoryInterface);

        $options = [];
        foreach ($categories->all() as $category) {
            $options[$category->id] = $category->name;
        }

        return new PostForm($options);
    }
}
