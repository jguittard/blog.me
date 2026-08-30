<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\AlterTable;
use PhpDb\Sql\Ddl\Column\Varchar;

final class Version20260201000001AddPostImageColumns extends AbstractMigration
{
    public function description(): string
    {
        return 'Add image_url / image_alt to posts';
    }

    public function up(AdapterInterface $db): void
    {
        $table = new AlterTable('posts');
        $table->addColumn(new Varchar('image_url', 500, true));
        $table->addColumn(new Varchar('image_alt', 255, true));

        $this->execute($db, $table);
    }

    public function down(AdapterInterface $db): void
    {
        $table = new AlterTable('posts');
        $table->dropColumn('image_url');
        $table->dropColumn('image_alt');

        $this->execute($db, $table);
    }
}
