<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration\Exception;

final class DuplicateMigrationIdentifierException extends \RuntimeException
{
    public static function forIdentifier(string $identifier, string $firstClass, string $secondClass): self
    {
        return new self(
            sprintf(
                'Migration identifier "%s" is used by both "%s" and "%s". Identifiers must be unique'
                . ' across all extensions - rename one of the classes or override getIdentifier().',
                $identifier,
                $firstClass,
                $secondClass,
            ),
            1751234513,
        );
    }
}
