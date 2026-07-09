<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

/**
 * Opt-in extension of MigrationInterface for migrations that can be rolled
 * back. Not every content migration is meaningfully reversible - implement
 * this interface only when down() actually makes sense.
 */
interface ReversibleMigrationInterface extends MigrationInterface
{
    public function down(MigrationContext $context): void;
}
