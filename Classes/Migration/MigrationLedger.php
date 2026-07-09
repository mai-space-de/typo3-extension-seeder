<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use Maispace\MaiSeeder\Migration\ValueObject\LedgerEntry;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Persistence for tx_maiseeder_migration_record - the per-record change
 * ledger written to by MigrationContext::upsert()/delete() and read by
 * MigrationContext::revertTrackedChanges().
 */
final class MigrationLedger
{
    private const TABLE = 'tx_maiseeder_migration_record';

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    /**
     * @param array<string, mixed> $lookupCriteria
     * @param array<string, mixed>|null $snapshotBefore
     */
    public function record(
        string $migrationIdentifier,
        string $tableName,
        array $lookupCriteria,
        ?int $recordUid,
        LedgerAction $action,
        ?array $snapshotBefore,
    ): void {
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'migration_identifier' => $migrationIdentifier,
            'table_name' => $tableName,
            'record_uid' => $recordUid,
            'lookup_criteria' => json_encode($lookupCriteria, JSON_THROW_ON_ERROR),
            'action' => $action->value,
            'snapshot_before' => $snapshotBefore !== null
                ? json_encode($snapshotBefore, JSON_THROW_ON_ERROR)
                : null,
            'crdate' => $this->now(),
        ]);
    }

    /**
     * @return LedgerEntry[] ordered in the order the changes were made
     */
    public function getEntriesForMigration(string $migrationIdentifier): array
    {
        $queryBuilder = $this->queryBuilder();
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq(
                'migration_identifier',
                $queryBuilder->createNamedParameter($migrationIdentifier),
            ))
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->mapRowToEntry(...), $rows);
    }

    public function clearEntriesForMigration(string $migrationIdentifier): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->delete(
            self::TABLE,
            ['migration_identifier' => $migrationIdentifier],
        );
    }

    private function queryBuilder(): \TYPO3\CMS\Core\Database\Query\QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable(self::TABLE)->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToEntry(array $row): LedgerEntry
    {
        return new LedgerEntry(
            uid: (int)$row['uid'],
            migrationIdentifier: (string)$row['migration_identifier'],
            tableName: (string)$row['table_name'],
            recordUid: $row['record_uid'] !== null ? (int)$row['record_uid'] : null,
            lookupCriteria: json_decode((string)$row['lookup_criteria'], true, 512, JSON_THROW_ON_ERROR),
            action: LedgerAction::from((string)$row['action']),
            snapshotBefore: $row['snapshot_before'] !== null
                ? json_decode((string)$row['snapshot_before'], true, 512, JSON_THROW_ON_ERROR)
                : null,
        );
    }

    private function now(): int
    {
        return (int)($GLOBALS['EXEC_TIME'] ?? time());
    }
}
