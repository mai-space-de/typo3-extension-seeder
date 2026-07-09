<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\Exception;

final class IrreversibleMigrationException extends \RuntimeException
{
    public static function forMigration(string $identifier): self
    {
        return new self(
            sprintf(
                'Migration "%s" does not implement ReversibleMigrationInterface and cannot be rolled back.',
                $identifier,
            ),
            1751234511,
        );
    }
}
