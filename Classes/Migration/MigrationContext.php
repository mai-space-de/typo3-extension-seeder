<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * The toolbox handed to a migration's up()/down() method.
 *
 * Deliberately low-level: it makes no assumptions about the shape of a
 * table (TCA-registered or not, with or without a "uid" column, MM tables
 * with composite keys, ...). Establishing correct relations between tables
 * is the migration author's responsibility - this class only guarantees
 * that writes are idempotent (upsert/delete match by a caller-supplied
 * lookup key, never by an environment-specific auto-increment uid) and
 * that they are tracked so they can be resolved later (resolveUid()) or
 * undone (revertTrackedChanges()).
 */
final class MigrationContext
{
    /** @var array<string, bool> */
    private array $uidColumnCache = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly MigrationLedger $ledger,
        private readonly string $migrationIdentifier,
    ) {
    }

    public function getConnection(string $table): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }

    /**
     * Escape hatch for anything not covered by upsert()/delete()/resolveUid(),
     * e.g. bulk selects. Default restrictions (enable-fields etc.) are removed,
     * since migrations operate below the TCA abstraction.
     */
    public function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->getConnection($table)->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Inserts a record if no row matches $lookup, or updates the matching
     * row otherwise. Always matches by $lookup, never by uid - this is what
     * makes seeding safe to re-run and portable across environments.
     *
     * @param array<string, mixed> $lookup stable, caller-chosen identity of the record
     * @param array<string, mixed> $values additional column values to write (merged with $lookup on insert)
     * @return int|null the record's uid, or null if the table has no "uid" column (e.g. plain MM tables)
     */
    public function upsert(string $table, array $lookup, array $values = []): ?int
    {
        $this->assertLookupNotEmpty($table, $lookup);
        $connection = $this->getConnection($table);
        $existing = $this->findOne($table, $lookup);

        if ($existing !== null) {
            $uid = $this->extractUid($existing);
            if ($values !== []) {
                $connection->update($table, $values, $lookup);
            }
            $this->ledger->record($this->migrationIdentifier, $table, $lookup, $uid, LedgerAction::Update, $existing);

            return $uid;
        }

        $connection->insert($table, array_merge($lookup, $values));
        $uid = $this->tableHasUidColumn($connection, $table) ? (int)$connection->lastInsertId() : null;
        $this->ledger->record($this->migrationIdentifier, $table, $lookup, $uid, LedgerAction::Insert, null);

        return $uid;
    }

    /**
     * Deletes all rows matching $lookup, snapshotting each one beforehand so
     * revertTrackedChanges() can restore them.
     *
     * @param array<string, mixed> $lookup
     * @return int number of deleted rows
     */
    public function delete(string $table, array $lookup): int
    {
        $this->assertLookupNotEmpty($table, $lookup);
        $connection = $this->getConnection($table);
        $rows = $this->findAll($table, $lookup);

        if ($rows === []) {
            return 0;
        }

        $hasUidColumn = $this->tableHasUidColumn($connection, $table);
        foreach ($rows as $row) {
            $rowIdentity = $hasUidColumn && isset($row['uid']) ? ['uid' => $row['uid']] : $lookup;
            $this->ledger->record(
                $this->migrationIdentifier,
                $table,
                $rowIdentity,
                $this->extractUid($row),
                LedgerAction::Delete,
                $row,
            );
        }

        return $connection->delete($table, $lookup);
    }

    /**
     * Looks up the uid of a record by a lookup key, e.g. one established by
     * an earlier migration's upsert(). Always reads current database state,
     * so this is safe to call across migrations regardless of run order.
     *
     * @param array<string, mixed> $lookup
     */
    public function resolveUid(string $table, array $lookup): ?int
    {
        $this->assertLookupNotEmpty($table, $lookup);
        $row = $this->findOne($table, $lookup);

        return $row !== null ? $this->extractUid($row) : null;
    }

    /**
     * Undoes every tracked change made by this migration, in reverse order:
     * inserted rows are deleted, deleted rows are restored from their
     * snapshot, updated rows are restored to their pre-update values.
     * Intended to be called from a ReversibleMigrationInterface::down()
     * implementation that has no more specific rollback logic of its own.
     */
    public function revertTrackedChanges(): void
    {
        $entries = $this->ledger->getEntriesForMigration($this->migrationIdentifier);

        foreach (array_reverse($entries) as $entry) {
            $connection = $this->getConnection($entry->tableName);

            match ($entry->action) {
                LedgerAction::Insert => $connection->delete($entry->tableName, $entry->lookupCriteria),
                LedgerAction::Delete => $connection->insert($entry->tableName, $entry->snapshotBefore ?? []),
                LedgerAction::Update => $entry->snapshotBefore !== null
                    ? $connection->update($entry->tableName, $entry->snapshotBefore, $entry->lookupCriteria)
                    : null,
            };
        }

        $this->ledger->clearEntriesForMigration($this->migrationIdentifier);
    }

    /**
     * @param array<string, mixed> $lookup
     * @return array<string, mixed>|null
     */
    private function findOne(string $table, array $lookup): ?array
    {
        $row = $this->applyLookup($this->getQueryBuilder($table)->select('*')->from($table), $lookup)
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $lookup
     * @return array<int, array<string, mixed>>
     */
    private function findAll(string $table, array $lookup): array
    {
        return $this->applyLookup($this->getQueryBuilder($table)->select('*')->from($table), $lookup)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $lookup
     */
    private function applyLookup(QueryBuilder $queryBuilder, array $lookup): QueryBuilder
    {
        foreach ($lookup as $column => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($value)));
        }

        return $queryBuilder;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractUid(array $row): ?int
    {
        return isset($row['uid']) ? (int)$row['uid'] : null;
    }

    private function tableHasUidColumn(Connection $connection, string $table): bool
    {
        if (!array_key_exists($table, $this->uidColumnCache)) {
            $columns = $connection->createSchemaManager()->listTableColumns($table);
            $this->uidColumnCache[$table] = isset($columns['uid']);
        }

        return $this->uidColumnCache[$table];
    }

    /**
     * @param array<string, mixed> $lookup
     */
    private function assertLookupNotEmpty(string $table, array $lookup): void
    {
        if ($lookup === []) {
            throw new \InvalidArgumentException(
                sprintf('Lookup criteria must not be empty - refusing to match every row in "%s".', $table),
                1751234530,
            );
        }
    }
}
