<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use App\Domain\Entity\Tag;
use App\Domain\Repository\TagRepositoryInterface;
use App\Domain\Value\Slug;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use Laminas\Hydrator\HydratorInterface;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\ResultSet\HydratingResultSet;
use PhpDb\Sql\Select;
use PhpDb\TableGateway\Feature\FeatureSet;
use PhpDb\TableGateway\TableGateway;
use ReflectionClass;

/**
 * @psalm-api Instantiated by the DI container / migrate CLI.
 */
final class PhpDbTagRepository implements TagRepositoryInterface
{
    use HydratesRowsTrait;

    private readonly HydratorInterface $hydrator;
    private readonly TableGateway $table;

    public function __construct(
        AdapterInterface $db,
        HydratorRegistry $hydrators,
    ) {
        $this->hydrator = $hydrators->tag();
        $prototype      = (new ReflectionClass(Tag::class))->newInstanceWithoutConstructor();
        $this->table    = new TableGateway(
            'tags',
            $db,
            new FeatureSet(),
            new HydratingResultSet($this->hydrator, $prototype),
        );
    }

    public function find(string $id): ?Tag
    {
        $row = $this->firstOf($this->table->select(['id' => $id]));

        return $row instanceof Tag ? $row : null;
    }

    public function findBySlug(Slug $slug): ?Tag
    {
        $row = $this->firstOf($this->table->select(['slug' => (string) $slug]));

        return $row instanceof Tag ? $row : null;
    }

    /**
     * @param  list<string> $names
     * @return list<Tag>
     */
    public function findOrCreateByNames(array $names): array
    {
        $tags = [];
        foreach ($names as $name) {
            $slug     = Slug::fromTitle($name);
            $existing = $this->findBySlug($slug);
            $tags[]   = $existing ?? $this->save(Tag::named($name));
        }

        return $tags;
    }

    /** @return list<Tag> */
    public function forPost(string $postId): array
    {
        $resultSet = $this->table->select(static function (Select $select) use ($postId): void {
            $select->join('post_tag', 'post_tag.tag_id = tags.id', [], Select::JOIN_INNER)
                ->where(['post_tag.post_id' => $postId])
                ->order('tags.name ASC');
        });

        $tags = [];
        /** @var mixed $row */
        foreach ($resultSet as $row) {
            if ($row instanceof Tag) {
                $tags[] = $row;
            }
        }

        return $tags;
    }

    public function save(Tag $tag): Tag
    {
        $this->upsert($this->table, $this->hydrator, $tag->id, $tag);

        return $tag;
    }

    public function delete(string $id): void
    {
        $this->table->delete(['id' => $id]);
    }
}
