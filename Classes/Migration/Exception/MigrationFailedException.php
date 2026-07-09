<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\Exception;

final class MigrationFailedException extends \RuntimeException
{
    public static function forMigration(string $identifier, \Throwable $previous): self
    {
        return new self(
            sprintf('Migration "%s" failed: %s', $identifier, $previous->getMessage()),
            1751234510,
            $previous,
        );
    }
}
