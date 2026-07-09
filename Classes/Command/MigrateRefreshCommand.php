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

#[AsCommand(name: 'mai-seeder:migrate:refresh', description: 'Roll back all executed migrations and re-run them.')]
final class MigrateRefreshCommand extends Command
{
    public function __construct(private readonly MigrationRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $confirmed = $input->getOption('force')
            || $io->confirm('This rolls back and re-runs every migration. Continue?', false);
        if (!$confirmed) {
            $io->warning('Command cancelled.');

            return Command::SUCCESS;
        }

        $outcome = $this->runner->refresh();
        $failedRollbacks = array_filter($outcome['rolledBack'], static fn ($result) => !$result->success);
        $failedMigrations = array_filter($outcome['migrated'], static fn ($result) => !$result->success);

        $io->success(sprintf(
            '%d migration(s) rolled back, %d migration(s) re-applied.',
            count($outcome['rolledBack']),
            count($outcome['migrated']),
        ));

        return ($failedRollbacks === [] && $failedMigrations === []) ? Command::SUCCESS : Command::FAILURE;
    }
}
