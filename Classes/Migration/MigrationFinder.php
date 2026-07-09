<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Migration;

use Maispace\MaiSeeder\Migration\Exception\DuplicateMigrationIdentifierException;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Discovers migrations without any manual registration: every active
 * extension may ship its own Classes/Migrations/ directory with classes
 * implementing MigrationInterface, and they are picked up automatically.
 *
 * The FQCN of a migration class is resolved from the PSR-4 autoload prefix
 * each package registers for its "Classes/" directory - this is how any
 * extension can contribute migrations without registering them anywhere.
 */
final class MigrationFinder
{
    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly ExtensionNamespaceResolver $namespaceResolver,
    ) {
    }

    /**
     * @return array<string, MigrationInterface> keyed and sorted by identifier
     */
    public function findAll(): array
    {
        $migrations = [];
        $sourceClassByIdentifier = [];

        foreach ($this->packageManager->getActivePackages() as $package) {
            $classesPath = rtrim($package->getPackagePath(), '/') . '/Classes/';
            $migrationsPath = $classesPath . 'Migrations/';

            if (!is_dir($migrationsPath)) {
                continue;
            }

            $namespacePrefix = $this->namespaceResolver->resolveForClassesDirectory($classesPath);
            if ($namespacePrefix === null) {
                continue;
            }

            foreach ($this->findMigrationClasses($migrationsPath, $namespacePrefix) as $className) {
                /** @var MigrationInterface $migration */
                $migration = new $className();
                $identifier = $migration->getIdentifier();

                if (isset($sourceClassByIdentifier[$identifier])) {
                    throw DuplicateMigrationIdentifierException::forIdentifier(
                        $identifier,
                        $sourceClassByIdentifier[$identifier],
                        $className,
                    );
                }

                $sourceClassByIdentifier[$identifier] = $className;
                $migrations[$identifier] = $migration;
            }
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * @return iterable<class-string<MigrationInterface>>
     */
    private function findMigrationClasses(string $migrationsPath, string $namespacePrefix): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($migrationsPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $relativePath = substr($fileInfo->getPathname(), strlen($migrationsPath));

            if (!str_ends_with($relativePath, '.php')) {
                continue;
            }

            $relativeClassName = str_replace('/', '\\', substr($relativePath, 0, -4));
            $className = $namespacePrefix . 'Migrations\\' . $relativeClassName;

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            if (
                $reflection->isAbstract()
                || $reflection->isInterface()
                || !$reflection->implementsInterface(MigrationInterface::class)
            ) {
                continue;
            }

            yield $className;
        }
    }
}
