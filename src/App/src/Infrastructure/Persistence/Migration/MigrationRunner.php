<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Migration;

use DateTimeImmutable;
use PhpDb\Adapter\AdapterInterface;

use function array_reverse;
use function is_array;
use function is_scalar;
use function usort;

/**
 * Applies / reverts {@see MigrationInterface}s and tracks state in a
 * `schema_migrations` table.
 *
 * @psalm-api Instantiated by the DI container / the migrate CLI.
 */
final class MigrationRunner
{
    private const string REGISTRY = 'schema_migrations';

    /** @var list<MigrationInterface> ordered ascending by version */
    private readonly array $migrations;

    /** @param iterable<MigrationInterface> $migrations */
    public function __construct(
        private readonly AdapterInterface $db,
        iterable $migrations,
    ) {
        $list = [];
        foreach ($migrations as $migration) {
            $list[] = $migration;
        }
        usort($list, static fn (MigrationInterface $a, MigrationInterface $b): int => $a->version() <=> $b->version());

        $this->migrations = $list;
    }

    /**
     * Apply every pending migration in order.
     *
     * @return list<string> versions that were applied
     */
    public function migrate(): array
    {
        $this->ensureRegistry();
        $applied = $this->appliedVersions();
        $ran     = [];

        foreach ($this->migrations as $migration) {
            $version = $migration->version();
            if (isset($applied[$version])) {
                continue;
            }

            $migration->up($this->db);
            $this->recordApplied($version);
            $ran[] = $version;
        }

        return $ran;
    }

    /**
     * Revert the last $steps applied migrations.
     *
     * @return list<string> versions that were reverted
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureRegistry();
        $applied  = $this->appliedVersions();
        $reverted = [];

        foreach (array_reverse($this->migrations) as $migration) {
            if ($steps <= 0) {
                break;
            }

            $version = $migration->version();
            if (! isset($applied[$version])) {
                continue;
            }

            $migration->down($this->db);
            $this->recordReverted($version);
            $reverted[] = $version;
            $steps--;
        }

        return $reverted;
    }

    /**
     * @return array<string, array{description: string, applied: bool}>
     */
    public function status(): array
    {
        $this->ensureRegistry();
        $applied = $this->appliedVersions();

        $rows = [];
        foreach ($this->migrations as $migration) {
            $version        = $migration->version();
            $rows[$version] = [
                'description' => $migration->description(),
                'applied'     => isset($applied[$version]),
            ];
        }

        return $rows;
    }

    private function ensureRegistry(): void
    {
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS `' . self::REGISTRY . '` ('
            . '`version` VARCHAR(14) NOT NULL, '
            . '`applied_at` DATETIME NOT NULL, '
            . 'PRIMARY KEY (`version`))',
        );
    }

    /** @return array<string, true> */
    private function appliedVersions(): array
    {
        $result   = $this->db->executeQuery('SELECT `version` FROM `' . self::REGISTRY . '`');
        $versions = [];

        /** @var mixed $row */
        foreach ($result as $row) {
            if (! is_array($row)) {
                continue;
            }

            /** @var mixed $version */
            $version = $row['version'] ?? null;
            if (is_scalar($version)) {
                $versions[(string) $version] = true;
            }
        }

        return $versions;
    }

    private function recordApplied(string $version): void
    {
        $statement = $this->db->prepareQuery(
            'INSERT INTO `' . self::REGISTRY . '` (`version`, `applied_at`) VALUES (?, ?)',
            [$version, (new DateTimeImmutable())->format('Y-m-d H:i:s')],
        );
        $this->db->executeQuery($statement);
    }

    private function recordReverted(string $version): void
    {
        $statement = $this->db->prepareQuery(
            'DELETE FROM `' . self::REGISTRY . '` WHERE `version` = ?',
            [$version],
        );
        $this->db->executeQuery($statement);
    }
}
