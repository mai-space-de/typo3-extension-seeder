<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\ValueObject;

/**
 * Read model combining a discovered migration class with its execution
 * state from the log table, used by "migrate:status" and the backend module.
 */
final class MigrationStatus
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $description,
        public readonly string $className,
        public readonly bool $reversible,
        public readonly bool $executed,
        public readonly ?\DateTimeImmutable $executedAt = null,
        public readonly ?int $batch = null,
        public readonly ?bool $success = null,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
