<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Command;

use Maispace\MaiSeeder\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;

#[AsCommand(name: 'mai-seeder:migrate', description: 'Run all pending migrations.')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly MigrationRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run migrations inside a transaction that is rolled back afterwards, without persisting anything.',
            )
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Run at most this many pending migrations.')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Do not ask for confirmation when running in a production context.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $step = $input->getOption('step') !== null ? (int)$input->getOption('step') : null;

        if (!$dryRun && !$input->getOption('force') && Environment::getContext()->isProduction()) {
            $confirmed = $io->confirm(
                'You are running in a production context. Do you really want to run pending migrations?',
                false,
            );
            if (!$confirmed) {
                $io->warning('Command cancelled.');

                return Command::SUCCESS;
            }
        }

        $results = $this->runner->migrate($dryRun, $step);

        if ($results === []) {
            $io->success('Nothing to migrate.');

            return Command::SUCCESS;
        }

        foreach ($results as $result) {
            if ($result->success) {
                $io->writeln(sprintf(
                    '  <fg=green>DONE</>  %s (%s) [%.1fms]',
                    $result->identifier,
                    $result->description,
                    $result->executionTimeMs,
                ));
            } else {
                $io->writeln(sprintf('  <fg=red>FAIL</>  %s (%s)', $result->identifier, $result->description));
                $io->error($result->errorMessage ?? 'Unknown error.');
            }
        }

        $failed = array_filter($results, static fn ($result) => !$result->success);
        if ($failed !== []) {
            return Command::FAILURE;
        }

        $io->success($dryRun
            ? sprintf('Dry-run complete: %d migration(s) would have been applied.', count($results))
            : sprintf('%d migration(s) applied.', count($results)));

        return Command::SUCCESS;
    }
}
