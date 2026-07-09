<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Persistence for tx_maiseeder_migration - the migration run log.
 */
final class MigrationRepository
{
    private const TABLE = 'tx_maiseeder_migration';

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    /**
     * @return array<string, array<string, mixed>> executed migration rows keyed by identifier
     */
    public function getExecutedMigrations(): array
    {
        $rows = $this->queryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('batch', 'ASC')
            ->addOrderBy('executed_at', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $migrations = [];
        foreach ($rows as $row) {
            $migrations[(string)$row['identifier']] = $row;
        }

        return $migrations;
    }

    /**
     * @return array<string, mixed>[] rows to roll back, most recently executed first
     */
    public function findForRollback(?int $step, ?int $batch): array
    {
        $queryBuilder = $this->queryBuilder()->select('*')->from(self::TABLE);

        if ($batch !== null) {
            $queryBuilder->where($queryBuilder->expr()->eq(
                'batch',
                $queryBuilder->createNamedParameter($batch, \PDO::PARAM_INT),
            ));
        } elseif ($step === null) {
            $latestBatch = $this->getLatestBatchNumber();
            if ($latestBatch === null) {
                return [];
            }
            $queryBuilder->where($queryBuilder->expr()->eq(
                'batch',
                $queryBuilder->createNamedParameter($latestBatch, \PDO::PARAM_INT),
            ));
        }

        $queryBuilder->orderBy('batch', 'DESC')->addOrderBy('executed_at', 'DESC')->addOrderBy('uid', 'DESC');

        if ($step !== null) {
            $queryBuilder->setMaxResults($step);
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function getNextBatchNumber(): int
    {
        return ($this->getLatestBatchNumber() ?? 0) + 1;
    }

    public function recordSuccess(
        string $identifier,
        string $migrationClass,
        string $description,
        int $batch,
        float $executionTimeMs,
    ): void {
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'identifier' => $identifier,
            'migration_class' => $migrationClass,
            'description' => $description,
            'batch' => $batch,
            'success' => 1,
            'error_message' => null,
            'execution_time_ms' => (int)round($executionTimeMs),
            'executed_at' => $this->now(),
        ]);
    }

    public function recordFailure(
        string $identifier,
        string $migrationClass,
        string $description,
        int $batch,
        float $executionTimeMs,
        string $errorMessage,
    ): void {
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'identifier' => $identifier,
            'migration_class' => $migrationClass,
            'description' => $description,
            'batch' => $batch,
            'success' => 0,
            'error_message' => $errorMessage,
            'execution_time_ms' => (int)round($executionTimeMs),
            'executed_at' => $this->now(),
        ]);
    }

    public function removeExecutionRecord(string $identifier): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->delete(
            self::TABLE,
            ['identifier' => $identifier],
        );
    }

    private function getLatestBatchNumber(): ?int
    {
        $max = $this->queryBuilder()
            ->selectLiteral('MAX(batch) AS max_batch')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchOne();

        return $max !== null ? (int)$max : null;
    }

    private function queryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getConnectionForTable(self::TABLE)->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    private function now(): int
    {
        return (int)($GLOBALS['EXEC_TIME'] ?? time());
    }
}
