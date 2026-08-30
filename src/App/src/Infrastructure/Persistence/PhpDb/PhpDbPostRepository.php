<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use App\Domain\Entity\Post;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Value\Slug;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use Laminas\Hydrator\HydratorInterface;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\ResultSet\HydratingResultSet;
use PhpDb\TableGateway\Feature\FeatureSet;
use PhpDb\TableGateway\TableGateway;
use ReflectionClass;
use Throwable;

use function array_fill;
use function array_unique;
use function array_values;
use function count;
use function implode;

/**
 * @psalm-api Instantiated by the DI container / migrate CLI.
 */
final class PhpDbPostRepository implements PostRepositoryInterface
{
    use HydratesRowsTrait;

    private readonly HydratorInterface $hydrator;
    private readonly TableGateway $table;

    public function __construct(
        private readonly AdapterInterface $db,
        HydratorRegistry $hydrators,
    ) {
        $this->hydrator = $hydrators->post();
        $prototype      = (new ReflectionClass(Post::class))->newInstanceWithoutConstructor();
        $this->table    = new TableGateway(
            'posts',
            $db,
            new FeatureSet(),
            new HydratingResultSet($this->hydrator, $prototype),
        );
    }

    public function find(int $id): ?Post
    {
        $row = $this->firstOf($this->table->select(['id' => $id]));

        return $row instanceof Post ? $row : null;
    }

    public function findBySlug(Slug $slug): ?Post
    {
        $row = $this->firstOf($this->table->select(['slug' => (string) $slug]));

        return $row instanceof Post ? $row : null;
    }

    /** @param list<int> $tagIds */
    public function save(Post $post, array $tagIds = []): Post
    {
        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            /** @var array<string, mixed> $data */
            $data = $this->hydrator->extract($post);
            unset($data['id']);

            if ($post->id === null) {
                $this->table->insert($data);
                $post = $post->withId((int) $connection->getLastGeneratedValue());
            } else {
                $this->table->update($data, ['id' => $post->id]);
            }

            $this->syncTags((int) $post->id, $tagIds);

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollback();

            throw $e;
        }

        return $post;
    }

    public function delete(int $id): void
    {
        $this->table->delete(['id' => $id]);
    }

    /** @param list<int> $tagIds */
    private function syncTags(int $postId, array $tagIds): void
    {
        $delete = $this->db->prepareQuery('DELETE FROM `post_tag` WHERE `post_id` = ?', [$postId]);
        $this->db->executeQuery($delete);

        $tagIds = array_values(array_unique($tagIds));
        if ($tagIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($tagIds), '(?, ?)'));
        $params       = [];
        foreach ($tagIds as $tagId) {
            $params[] = $postId;
            $params[] = $tagId;
        }

        $insert = $this->db->prepareQuery(
            'INSERT INTO `post_tag` (`post_id`, `tag_id`) VALUES ' . $placeholders,
            $params,
        );
        $this->db->executeQuery($insert);
    }
}
