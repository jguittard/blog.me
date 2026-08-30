<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;

final class Version20260101000005CreatePostTagTable extends AbstractMigration
{
    public function description(): string
    {
        return 'Create post_tag join table';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new CreateTable('post_tag');

        $table->addColumn(new Integer('post_id'));
        $table->addColumn(new Integer('tag_id'));

        $table->addConstraint(new PrimaryKey(['post_id', 'tag_id']));
        $table->addConstraint(
            new ForeignKey('fk_post_tag_post', 'post_id', 'posts', 'id', 'CASCADE', 'CASCADE'),
        );
        $table->addConstraint(
            new ForeignKey('fk_post_tag_tag', 'tag_id', 'tags', 'id', 'CASCADE', 'CASCADE'),
        );

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $this->execute($db, new DropTable('post_tag'));
    }
}
