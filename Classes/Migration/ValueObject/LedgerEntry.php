<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\ValueObject;

use Maispace\MaiSeeder\Migration\LedgerAction;

/**
 * A single tracked change made by a migration to a database record.
 * Used both to resolve relations across migrations (indirectly, via
 * MigrationContext::resolveUid()) and to undo changes in down().
 */
final class LedgerEntry
{
    /**
     * @param array<string, mixed> $lookupCriteria
     * @param array<string, mixed>|null $snapshotBefore
     */
    public function __construct(
        public readonly ?int $uid,
        public readonly string $migrationIdentifier,
        public readonly string $tableName,
        public readonly ?int $recordUid,
        public readonly array $lookupCriteria,
        public readonly LedgerAction $action,
        public readonly ?array $snapshotBefore,
    ) {
    }
}
