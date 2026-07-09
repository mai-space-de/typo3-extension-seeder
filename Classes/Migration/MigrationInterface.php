<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

/**
 * Contract for a single, versioned content migration.
 *
 * Implementations are expected to live in Classes/Migrations/ of any active
 * extension (including this one) - they are discovered automatically by the
 * MigrationFinder, there is no need to register them manually.
 */
interface MigrationInterface
{
    /**
     * Stable, unique identifier used for ordering and as the primary key in
     * the migration log. AbstractMigration derives this from the class name;
     * override this method if a custom identifier is required.
     */
    public function getIdentifier(): string;

    public function getDescription(): string;

    /**
     * Allows a migration to opt out at runtime, e.g. based on the
     * application context or extension configuration.
     */
    public function shouldRun(): bool;

    public function up(MigrationContext $context): void;
}
