<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Text;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;

final class Version20260101000004CreatePostsTable extends AbstractMigration
{
    public function description(): string
    {
        return 'Create posts table';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new CreateTable('posts');

        $table->addColumn($this->idColumn('id'));
        $table->addColumn($this->idColumn('author_id'));
        $table->addColumn($this->idColumn('category_id', true));
        $table->addColumn(new Varchar('slug', 200));
        $table->addColumn(new Varchar('title', 200));
        $table->addColumn(new Varchar('excerpt', 500, true));
        $table->addColumn(new Text('body'));
        $table->addColumn(new Varchar('status', 20, false, 'draft'));
        $table->addColumn(new Datetime('published_at', true));
        $table->addColumn(new Datetime('created_at'));
        $table->addColumn(new Datetime('updated_at'));

        $table->addConstraint(new PrimaryKey('id'));
        $table->addConstraint(new UniqueKey(['slug'], 'uq_posts_slug'));
        $table->addConstraint(
            new ForeignKey('fk_posts_author', 'author_id', 'users', 'id', 'CASCADE', 'CASCADE'),
        );
        $table->addConstraint(
            new ForeignKey('fk_posts_category', 'category_id', 'categories', 'id', 'SET NULL', 'CASCADE'),
        );

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $this->execute($db, new DropTable('posts'));
    }
}
