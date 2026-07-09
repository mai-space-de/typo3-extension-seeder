<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use Maispace\MaiSeeder\Migration\Exception\InvalidMigrationStateException;
use Maispace\MaiSeeder\Migration\Exception\IrreversibleMigrationException;
use Maispace\MaiSeeder\Migration\Exception\MigrationNotFoundException;
use Maispace\MaiSeeder\Migration\ValueObject\MigrationRunResult;
use Maispace\MaiSeeder\Migration\ValueObject\MigrationStatus;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Orchestrates discovery, execution, logging and rollback of migrations.
 * Shared by the CLI commands and the backend module - neither of them talks
 * to MigrationFinder/MigrationRepository/MigrationLedger directly.
 *
 * Each migration runs inside a transaction on the "Default" database
 * connection. This is what makes --dry-run possible for arbitrary,
 * imperative migration code: the migration actually runs, and the
 * transaction is rolled back afterwards instead of committed. Writes a
 * migration makes on a *different* connection (rare multi-DB setups) are
 * not covered by this guarantee.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly MigrationFinder $finder,
        private readonly MigrationRepository $repository,
        private readonly MigrationLedger $ledger,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return MigrationStatus[]
     */
    public function status(): array
    {
        $all = $this->finder->findAll();
        $executed = $this->repository->getExecutedMigrations();
        $statuses = [];

        foreach ($all as $identifier => $migration) {
            $row = $executed[$identifier] ?? null;
            $statuses[] = new MigrationStatus(
                identifier: $identifier,
                description: $migration->getDescription(),
                className: $migration::class,
                reversible: $migration instanceof ReversibleMigrationInterface,
                executed: $row !== null,
                executedAt: $row !== null ? $this->toDateTime((int)$row['executed_at']) : null,
                batch: $row !== null ? (int)$row['batch'] : null,
                success: $row !== null ? (bool)$row['success'] : null,
                errorMessage: $row['error_message'] ?? null,
            );
        }

        // Surface migrations that were executed but whose class can no
        // longer be found (deleted/renamed since), so they don't vanish silently.
        foreach ($executed as $identifier => $row) {
            if (isset($all[$identifier])) {
                continue;
            }
            $statuses[] = new MigrationStatus(
                identifier: $identifier,
                description: (string)$row['description'],
                className: (string)$row['migration_class'],
                reversible: false,
                executed: true,
                executedAt: $this->toDateTime((int)$row['executed_at']),
                batch: (int)$row['batch'],
                success: (bool)$row['success'],
                errorMessage: $row['error_message'] ?? null,
            );
        }

        return $statuses;
    }

    /**
     * @return MigrationRunResult[]
     */
    public function migrate(bool $dryRun = false, ?int $step = null): array
    {
        $pending = $this->getPendingMigrations();
        if ($step !== null) {
            $pending = array_slice($pending, 0, $step, true);
        }

        $batch = $this->repository->getNextBatchNumber();
        $results = [];

        foreach ($pending as $migration) {
            $result = $this->runOne($migration, $batch, $dryRun, up: true);
            $results[] = $result;

            if (!$result->success) {
                break;
            }
        }

        return $results;
    }

    /**
     * Runs a single, specific pending migration - used by the backend
     * module's per-row "Execute" action.
     */
    public function migrateOne(string $identifier, bool $dryRun = false): MigrationRunResult
    {
        $migration = $this->finder->findAll()[$identifier] ?? throw MigrationNotFoundException::forIdentifier($identifier);

        if (isset($this->repository->getExecutedMigrations()[$identifier])) {
            throw InvalidMigrationStateException::alreadyExecuted($identifier);
        }

        return $this->runOne($migration, $this->repository->getNextBatchNumber(), $dryRun, up: true);
    }

    /**
     * Rolls back a single, specific executed migration - used by the
     * backend module's per-row "Roll back" action.
     */
    public function rollbackOne(string $identifier): MigrationRunResult
    {
        $migration = $this->finder->findAll()[$identifier] ?? throw MigrationNotFoundException::forIdentifier($identifier);
        $executed = $this->repository->getExecutedMigrations()[$identifier]
            ?? throw InvalidMigrationStateException::notExecuted($identifier);

        return $this->runOne($migration, (int)$executed['batch'], dryRun: false, up: false);
    }

    /**
     * @return MigrationRunResult[]
     */
    public function rollback(?int $step = null, ?int $batch = null): array
    {
        $rows = $this->repository->findForRollback($step, $batch);
        $available = $this->finder->findAll();
        $results = [];

        foreach ($rows as $row) {
            $identifier = (string)$row['identifier'];
            $migration = $available[$identifier] ?? throw MigrationNotFoundException::forIdentifier($identifier);

            $result = $this->runOne($migration, (int)$row['batch'], dryRun: false, up: false);
            $results[] = $result;

            if (!$result->success) {
                break;
            }
        }

        return $results;
    }

    /**
     * Rolls back every executed migration, batch by batch.
     *
     * @return MigrationRunResult[]
     */
    public function reset(): array
    {
        $results = [];

        while (($batchResults = $this->rollback()) !== []) {
            $results = [...$results, ...$batchResults];
            $lastResult = $batchResults[array_key_last($batchResults)];
            if (!$lastResult->success) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array{rolledBack: MigrationRunResult[], migrated: MigrationRunResult[]}
     */
    public function refresh(): array
    {
        return [
            'rolledBack' => $this->reset(),
            'migrated' => $this->migrate(),
        ];
    }

    /**
     * @return MigrationInterface[] keyed by identifier
     */
    private function getPendingMigrations(): array
    {
        $executed = $this->repository->getExecutedMigrations();
        $pending = [];

        foreach ($this->finder->findAll() as $identifier => $migration) {
            if (isset($executed[$identifier]) || !$migration->shouldRun()) {
                continue;
            }
            $pending[$identifier] = $migration;
        }

        return $pending;
    }

    private function runOne(MigrationInterface $migration, int $batch, bool $dryRun, bool $up): MigrationRunResult
    {
        $identifier = $migration->getIdentifier();
        $description = $migration->getDescription();
        $context = new MigrationContext($this->connectionPool, $this->ledger, $identifier);
        $connection = $this->transactionalConnection();

        $start = microtime(true);
        $connection->beginTransaction();

        try {
            if ($up) {
                $migration->up($context);
            } elseif ($migration instanceof ReversibleMigrationInterface) {
                $migration->down($context);
            } else {
                throw IrreversibleMigrationException::forMigration($identifier);
            }

            $executionTimeMs = $this->elapsedMs($start);

            if ($dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
                if ($up) {
                    $this->repository->recordSuccess($identifier, $migration::class, $description, $batch, $executionTimeMs);
                } else {
                    $this->ledger->clearEntriesForMigration($identifier);
                    $this->repository->removeExecutionRecord($identifier);
                }
            }

            return new MigrationRunResult($identifier, $description, true, $executionTimeMs);
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $executionTimeMs = $this->elapsedMs($start);

            if (!$dryRun && $up) {
                $this->repository->recordFailure(
                    $identifier,
                    $migration::class,
                    $description,
                    $batch,
                    $executionTimeMs,
                    $e->getMessage(),
                );
            }

            return new MigrationRunResult($identifier, $description, false, $executionTimeMs, $e->getMessage());
        }
    }

    private function transactionalConnection(): Connection
    {
        return $this->connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
    }

    private function elapsedMs(float $start): float
    {
        return (microtime(true) - $start) * 1000;
    }

    private function toDateTime(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }
}
