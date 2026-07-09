<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Command;

use Maispace\MaiSeeder\Migration\ExtensionNamespaceResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'mai-seeder:make:migration',
    description: "Scaffold a new migration class in an extension's Classes/Migrations/ directory.",
)]
final class MakeMigrationCommand extends Command
{
    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly ExtensionNamespaceResolver $namespaceResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Descriptive name, e.g. "create product category relation".',
            )
            ->addOption('extension', null, InputOption::VALUE_REQUIRED, 'Extension key to place the migration in.')
            ->addOption(
                'reversible',
                null,
                InputOption::VALUE_NONE,
                'Also implement ReversibleMigrationInterface with a down() stub.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = $input->getOption('extension');

        if (!is_string($extensionKey) || $extensionKey === '') {
            $io->error('The --extension option is required, e.g. --extension=my_extension.');

            return Command::FAILURE;
        }

        try {
            $package = $this->packageManager->getPackage($extensionKey);
        } catch (UnknownPackageException) {
            $io->error(sprintf('Extension "%s" is not active.', $extensionKey));

            return Command::FAILURE;
        }

        $classesPath = rtrim($package->getPackagePath(), '/') . '/Classes/';
        $namespace = $this->namespaceResolver->resolveForClassesDirectory($classesPath);

        if ($namespace === null) {
            $io->error(sprintf(
                'Could not resolve a PSR-4 namespace for "%s". Make sure its composer.json autoload.psr-4'
                . ' section maps "Classes/".',
                $extensionKey,
            ));

            return Command::FAILURE;
        }

        $className = $this->buildClassName((string)$input->getArgument('name'));
        $migrationsPath = $classesPath . 'Migrations/';
        $targetFile = $migrationsPath . $className . '.php';

        if (file_exists($targetFile)) {
            $io->error(sprintf('File already exists: %s', $targetFile));

            return Command::FAILURE;
        }

        GeneralUtility::mkdir_deep($migrationsPath);
        GeneralUtility::writeFile(
            $targetFile,
            $this->buildContent($namespace, $className, (bool)$input->getOption('reversible')),
        );

        $io->success(sprintf('Created %s', $targetFile));

        return Command::SUCCESS;
    }

    private function buildClassName(string $rawName): string
    {
        $words = preg_replace('/[^a-zA-Z0-9]+/', ' ', $rawName) ?? '';
        $studly = str_replace(' ', '', ucwords(trim($words)));

        return 'Migration' . date('YmdHis') . $studly;
    }

    private function buildContent(string $namespace, string $className, bool $reversible): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace ' . $namespace . 'Migrations;',
            '',
            'use Maispace\MaiSeeder\Migration\AbstractMigration;',
            'use Maispace\MaiSeeder\Migration\MigrationContext;',
        ];

        if ($reversible) {
            $lines[] = 'use Maispace\MaiSeeder\Migration\ReversibleMigrationInterface;';
        }

        $lines[] = '';
        $lines[] = $reversible
            ? sprintf('final class %s extends AbstractMigration implements ReversibleMigrationInterface', $className)
            : sprintf('final class %s extends AbstractMigration', $className);
        $lines[] = '{';
        $lines[] = '    public function getDescription(): string';
        $lines[] = '    {';
        $lines[] = "        return '';";
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function up(MigrationContext $context): void';
        $lines[] = '    {';
        $lines[] = '        // $context->upsert(';
        $lines[] = "        //     table: 'tx_myext_domain_model_category',";
        $lines[] = "        //     lookup: ['import_identifier' => 'cat-shoes'],";
        $lines[] = "        //     values: ['title' => 'Shoes'],";
        $lines[] = '        // );';
        $lines[] = '    }';

        if ($reversible) {
            $lines[] = '';
            $lines[] = '    public function down(MigrationContext $context): void';
            $lines[] = '    {';
            $lines[] = '        $context->revertTrackedChanges();';
            $lines[] = '    }';
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
