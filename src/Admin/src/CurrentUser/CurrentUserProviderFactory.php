<?php

declare(strict_types=1);

namespace Admin\CurrentUser;

use App\Domain\Repository\UserRepositoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function is_string;

final class CurrentUserProviderFactory
{
    private const string DEFAULT_EMAIL = 'julien@guittard.me';

    public function __invoke(ContainerInterface $container): CurrentUserProvider
    {
        $users = $container->get(UserRepositoryInterface::class);
        assert($users instanceof UserRepositoryInterface);

        return new CurrentUserProvider($users, $this->email($container));
    }

    private function email(ContainerInterface $container): string
    {
        /** @var mixed $config */
        $config = $container->get('config');
        if (! is_array($config)) {
            return self::DEFAULT_EMAIL;
        }

        /** @var mixed $admin */
        $admin = $config['admin'] ?? null;
        if (! is_array($admin)) {
            return self::DEFAULT_EMAIL;
        }

        /** @var mixed $email */
        $email = $admin['current_user_email'] ?? null;

        return is_string($email) && $email !== '' ? $email : self::DEFAULT_EMAIL;
    }
}
