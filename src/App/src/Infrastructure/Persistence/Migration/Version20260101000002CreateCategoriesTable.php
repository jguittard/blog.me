<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Text;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;

final class Version20260101000002CreateCategoriesTable extends AbstractMigration
{
    public function description(): string
    {
        return 'Create categories table';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new CreateTable('categories');

        $id = new Integer('id');
        $id->setOption('autoincrement', true);

        $table->addColumn($id);
        $table->addColumn(new Varchar('slug', 120));
        $table->addColumn(new Varchar('name', 120));
        $table->addColumn(new Text('description', null, true));

        $table->addConstraint(new PrimaryKey('id'));
        $table->addConstraint(new UniqueKey(['slug'], 'uq_categories_slug'));

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $this->execute($db, new DropTable('categories'));
    }
}
