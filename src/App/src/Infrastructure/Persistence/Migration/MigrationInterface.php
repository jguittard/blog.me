<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use PhpDb\Adapter\AdapterInterface;

interface MigrationInterface
{
    /** Sortable identifier, e.g. "20260101000004". */
    public function version(): string;

    public function description(): string;

    public function up(AdapterInterface $db): void;

    public function down(AdapterInterface $db): void;
}
