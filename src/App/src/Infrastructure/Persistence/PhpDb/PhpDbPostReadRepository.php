<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use App\Domain\ReadModel\PostListItem;
use App\Domain\ReadModel\PostView;
use App\Domain\Repository\PostReadRepositoryInterface;
use App\Domain\Value\PostStatus;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\ResultSet\HydratingResultSet;
use PhpDb\Sql\Expression;
use PhpDb\Sql\Select;
use PhpDb\Sql\Sql;
use ReflectionClass;
use RuntimeException;

/**
 * @psalm-api Instantiated by the DI container / migrate CLI.
 */
final class PhpDbPostReadRepository implements PostReadRepositoryInterface
{
    private const TAGS_EXPR    = "GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ',')";
    private const PREVIEW_EXPR = 'LEFT(p.body, 300)';

    private readonly Sql $sql;

    public function __construct(
        AdapterInterface $db,
        private readonly HydratorRegistry $hydrators,
    ) {
        $this->sql = new Sql($db);
    }

    /** @return list<PostListItem> */
    public function listPublished(int $limit = 20, int $offset = 0): array
    {
        $select = $this->listSelect()
            ->where(['p.status' => PostStatus::Published->value])
            ->order('p.published_at DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->hydrateList($select);
    }

    /** @return list<PostListItem> */
    public function listByCategory(string $categorySlug, int $limit = 20, int $offset = 0): array
    {
        $select = $this->listSelect()
            ->where([
                'p.status' => PostStatus::Published->value,
                'c.slug'   => $categorySlug,
            ])
            ->order('p.published_at DESC')
            ->limit($limit)
            ->offset($offset);

        return $this->hydrateList($select);
    }

    public function viewBySlug(string $slug): ?PostView
    {
        $select = (new Select(['p' => 'posts']))
            ->columns([
                'id',
                'slug',
                'title',
                'excerpt',
                'body',
                'status',
                'published_at',
                'updated_at',
                'tags' => new Expression(self::TAGS_EXPR),
            ])
            ->join(
                ['u' => 'users'],
                'p.author_id = u.id',
                ['author_name' => 'display_name', 'author_email' => 'email'],
                Select::JOIN_INNER,
            )
            ->join(
                ['c' => 'categories'],
                'p.category_id = c.id',
                ['category_name' => 'name', 'category_slug' => 'slug'],
                Select::JOIN_LEFT,
            )
            ->join(['pt' => 'post_tag'], 'pt.post_id = p.id', [], Select::JOIN_LEFT)
            ->join(['t' => 'tags'], 't.id = pt.tag_id', [], Select::JOIN_LEFT)
            ->where(['p.slug' => $slug])
            ->group('p.id');

        $resultSet = new HydratingResultSet(
            $this->hydrators->postView(),
            (new ReflectionClass(PostView::class))->newInstanceWithoutConstructor(),
        );
        $resultSet->initialize($this->execute($select));

        /** @var mixed $row */
        foreach ($resultSet as $row) {
            return $row instanceof PostView ? $row : null;
        }

        return null;
    }

    private function listSelect(): Select
    {
        return (new Select(['p' => 'posts']))
            ->columns([
                'id',
                'slug',
                'title',
                'excerpt',
                'status',
                'published_at',
                'body_preview' => new Expression(self::PREVIEW_EXPR),
                'tags'         => new Expression(self::TAGS_EXPR),
            ])
            ->join(
                ['u' => 'users'],
                'p.author_id = u.id',
                ['author_name' => 'display_name'],
                Select::JOIN_INNER,
            )
            ->join(
                ['c' => 'categories'],
                'p.category_id = c.id',
                ['category_name' => 'name'],
                Select::JOIN_LEFT,
            )
            ->join(['pt' => 'post_tag'], 'pt.post_id = p.id', [], Select::JOIN_LEFT)
            ->join(['t' => 'tags'], 't.id = pt.tag_id', [], Select::JOIN_LEFT)
            ->group('p.id');
    }

    /**
     * @return list<PostListItem>
     */
    private function hydrateList(Select $select): array
    {
        $resultSet = new HydratingResultSet(
            $this->hydrators->postListItem(),
            (new ReflectionClass(PostListItem::class))->newInstanceWithoutConstructor(),
        );
        $resultSet->initialize($this->execute($select));

        $items = [];
        /** @var mixed $row */
        foreach ($resultSet as $row) {
            if ($row instanceof PostListItem) {
                $items[] = $row;
            }
        }

        return $items;
    }

    private function execute(Select $select): ResultInterface
    {
        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if (! $result instanceof ResultInterface) {
            throw new RuntimeException('Query did not produce a result set.');
        }

        return $result;
    }
}
