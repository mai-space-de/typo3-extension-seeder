<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\ValueObject;

/**
 * Outcome of running a single migration's up() or down(), as reported by
 * MigrationRunner to both the CLI commands and the backend module.
 */
final class MigrationRunResult
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $description,
        public readonly bool $success,
        public readonly float $executionTimeMs,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
