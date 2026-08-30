<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;

final class Version20260101000003CreateTagsTable extends AbstractMigration
{
    public function description(): string
    {
        return 'Create tags table';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new CreateTable('tags');

        $id = new Integer('id');
        $id->setOption('autoincrement', true);

        $table->addColumn($id);
        $table->addColumn(new Varchar('slug', 80));
        $table->addColumn(new Varchar('name', 80));

        $table->addConstraint(new PrimaryKey('id'));
        $table->addConstraint(new UniqueKey(['slug'], 'uq_tags_slug'));

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $this->execute($db, new DropTable('tags'));
    }
}
