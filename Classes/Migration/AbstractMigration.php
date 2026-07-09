<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

/**
 * Convenience base class that derives getIdentifier() from the class name,
 * following the convention "Migration<YmdHis><StudlyDescription>", e.g.
 * "Migration20260709120000CreateProductCategoryRelation".
 *
 * Extend ReversibleMigrationInterface instead of/in addition to this class
 * if down() should be supported.
 */
abstract class AbstractMigration implements MigrationInterface
{
    public function getIdentifier(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();

        if (!preg_match('/^Migration(?P<timestamp>\d{14})(?P<name>.+)$/', $className, $matches)) {
            throw new \LogicException(
                sprintf(
                    'Migration class name "%s" does not follow the expected pattern'
                    . ' "Migration<YmdHis><Name>", e.g. "Migration20260709120000CreateFooTable".'
                    . ' Override getIdentifier() to provide a custom identifier.',
                    $className,
                ),
                1751234501,
            );
        }

        $snakeCaseName = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $matches['name']));

        return $matches['timestamp'] . '_' . $snakeCaseName;
    }

    public function shouldRun(): bool
    {
        return true;
    }
}
