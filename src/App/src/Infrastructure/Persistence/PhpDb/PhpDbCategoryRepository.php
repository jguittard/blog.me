<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\PhpDb;

use App\Domain\Entity\Category;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Domain\Value\Slug;
use App\Infrastructure\Persistence\Hydrator\HydratorRegistry;
use Laminas\Hydrator\HydratorInterface;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\ResultSet\HydratingResultSet;
use PhpDb\TableGateway\Feature\FeatureSet;
use PhpDb\TableGateway\TableGateway;
use ReflectionClass;

/**
 * @psalm-api Instantiated by the DI container / migrate CLI.
 */
final class PhpDbCategoryRepository implements CategoryRepositoryInterface
{
    use HydratesRowsTrait;

    private readonly HydratorInterface $hydrator;
    private readonly TableGateway $table;

    public function __construct(
        private readonly AdapterInterface $db,
        HydratorRegistry $hydrators,
    ) {
        $this->hydrator = $hydrators->category();
        $prototype      = (new ReflectionClass(Category::class))->newInstanceWithoutConstructor();
        $this->table    = new TableGateway(
            'categories',
            $db,
            new FeatureSet(),
            new HydratingResultSet($this->hydrator, $prototype),
        );
    }

    public function find(int $id): ?Category
    {
        $row = $this->firstOf($this->table->select(['id' => $id]));

        return $row instanceof Category ? $row : null;
    }

    public function findBySlug(Slug $slug): ?Category
    {
        $row = $this->firstOf($this->table->select(['slug' => (string) $slug]));

        return $row instanceof Category ? $row : null;
    }

    /** @return list<Category> */
    public function all(): array
    {
        $categories = [];
        /** @var mixed $row */
        foreach ($this->table->select() as $row) {
            if ($row instanceof Category) {
                $categories[] = $row;
            }
        }

        return $categories;
    }

    public function save(Category $category): Category
    {
        /** @var array<string, mixed> $data */
        $data = $this->hydrator->extract($category);
        unset($data['id']);

        if ($category->id === null) {
            $this->table->insert($data);

            return $category->withId((int) $this->db->getDriver()->getConnection()->getLastGeneratedValue());
        }

        $this->table->update($data, ['id' => $category->id]);

        return $category;
    }

    public function delete(int $id): void
    {
        $this->table->delete(['id' => $id]);
    }
}
