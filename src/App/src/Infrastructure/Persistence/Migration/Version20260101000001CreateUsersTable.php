<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;

final class Version20260101000001CreateUsersTable extends AbstractMigration
{
    public function description(): string
    {
        return 'Create users table';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new CreateTable('users');

        $id = new Integer('id');
        $id->setOption('autoincrement', true);

        $table->addColumn($id);
        $table->addColumn(new Varchar('email', 255));
        $table->addColumn(new Varchar('display_name', 100));
        $table->addColumn(new Varchar('password_hash', 255));
        $table->addColumn(new Datetime('created_at'));

        $table->addConstraint(new PrimaryKey('id'));
        $table->addConstraint(new UniqueKey(['email'], 'uq_users_email'));

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $this->execute($db, new DropTable('users'));
    }
}
