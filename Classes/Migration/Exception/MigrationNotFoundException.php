<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\Exception;

final class MigrationNotFoundException extends \RuntimeException
{
    public static function forIdentifier(string $identifier): self
    {
        return new self(
            sprintf(
                'No migration class found for identifier "%s". It may have been deleted or renamed'
                . ' since it was executed.',
                $identifier,
            ),
            1751234512,
        );
    }
}
