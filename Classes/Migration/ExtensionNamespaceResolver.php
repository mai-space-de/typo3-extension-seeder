<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use TYPO3\CMS\Core\Core\ClassLoadingInformation;

/**
 * Resolves the PSR-4 namespace prefix an extension registered for its
 * "Classes/" directory, by matching against Composer's actual, resolved
 * autoload map. Works for both composer-managed and classic-mode extensions.
 *
 * Used by MigrationFinder (to build FQCNs of discovered migrations) and by
 * MakeMigrationCommand (to scaffold a new migration in the right namespace).
 */
final class ExtensionNamespaceResolver
{
    public function resolveForClassesDirectory(string $classesPath): ?string
    {
        $realClassesPath = realpath($classesPath);
        if ($realClassesPath === false) {
            return null;
        }

        foreach (ClassLoadingInformation::getClassLoader()->getPrefixesPsr4() as $namespace => $paths) {
            foreach ($paths as $path) {
                if (realpath($path) === $realClassesPath) {
                    return $namespace;
                }
            }
        }

        return null;
    }
}
