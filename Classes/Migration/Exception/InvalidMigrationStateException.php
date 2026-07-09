<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\Exception;

final class InvalidMigrationStateException extends \RuntimeException
{
    public static function alreadyExecuted(string $identifier): self
    {
        return new self(
            sprintf('Migration "%s" has already been executed.', $identifier),
            1751234540,
        );
    }

    public static function notExecuted(string $identifier): self
    {
        return new self(
            sprintf('Migration "%s" has not been executed.', $identifier),
            1751234541,
        );
    }
}
