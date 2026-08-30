<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Hydrator;

use App\Domain\Value\Email;
use App\Domain\Value\PostStatus;
use App\Domain\Value\Slug;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use Laminas\Hydrator\ReflectionHydrator;
use Laminas\Hydrator\Strategy\BackedEnumStrategy;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\Hydrator\Strategy\DateTimeImmutableFormatterStrategy;
use Laminas\Hydrator\Strategy\NullableStrategy;
use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * Builds and caches the {@see ReflectionHydrator}s used by the php-db
 * repositories. `ReflectionHydrator` populates `readonly` promoted properties
 * on a constructor-less prototype, which is exactly what
 * {@see \PhpDb\ResultSet\HydratingResultSet} needs.
 *
 * Zero-argument constructor: register as an invokable.
 */
final class HydratorRegistry
{
    /** MySQL `DATETIME` literal format. */
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /** @var array<string, ReflectionHydrator> */
    private array $cache = [];

    public function user(): ReflectionHydrator
    {
        return $this->cache['user'] ??= $this->make([
            'email'     => new ValueObjectStrategy(Email::fromString(...)),
            'createdAt' => $this->dateTime(),
        ]);
    }

    public function category(): ReflectionHydrator
    {
        return $this->cache['category'] ??= $this->make([
            'slug' => new ValueObjectStrategy(Slug::fromString(...)),
        ]);
    }

    public function tag(): ReflectionHydrator
    {
        return $this->cache['tag'] ??= $this->make([
            'slug' => new ValueObjectStrategy(Slug::fromString(...)),
        ]);
    }

    public function post(): ReflectionHydrator
    {
        return $this->cache['post'] ??= $this->make([
            'slug'        => new ValueObjectStrategy(Slug::fromString(...)),
            'status'      => new BackedEnumStrategy(PostStatus::class),
            'publishedAt' => $this->nullableDateTime(),
            'createdAt'   => $this->dateTime(),
            'updatedAt'   => $this->dateTime(),
        ]);
    }

    public function postListItem(): ReflectionHydrator
    {
        return $this->cache['postListItem'] ??= $this->make([
            'status'      => new BackedEnumStrategy(PostStatus::class),
            'publishedAt' => $this->nullableDateTime(),
            'tags'        => new CsvListStrategy(),
        ]);
    }

    public function postView(): ReflectionHydrator
    {
        return $this->cache['postView'] ??= $this->make([
            'status'      => new BackedEnumStrategy(PostStatus::class),
            'publishedAt' => $this->nullableDateTime(),
            'updatedAt'   => $this->dateTime(),
            'tags'        => new CsvListStrategy(),
        ]);
    }

    /** @param array<string, StrategyInterface> $strategies keyed by property name */
    private function make(array $strategies): ReflectionHydrator
    {
        $hydrator = new ReflectionHydrator();
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

        foreach ($strategies as $property => $strategy) {
            $hydrator->addStrategy($property, $strategy);
        }

        return $hydrator;
    }

    private function dateTime(): DateTimeImmutableFormatterStrategy
    {
        return new DateTimeImmutableFormatterStrategy(
            new DateTimeFormatterStrategy(self::DATETIME_FORMAT),
        );
    }

    private function nullableDateTime(): NullableStrategy
    {
        return new NullableStrategy($this->dateTime());
    }
}
