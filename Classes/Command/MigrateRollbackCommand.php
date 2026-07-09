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

#[AsCommand(
    name: 'mai-seeder:migrate:rollback',
    description: 'Roll back the last batch of migrations, or a specific batch/number of steps.',
)]
final class MigrateRollbackCommand extends Command
{
    public function __construct(private readonly MigrationRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'step',
                null,
                InputOption::VALUE_REQUIRED,
                'Roll back this many of the most recently executed migrations, across batches.',
            )
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Roll back all migrations in this batch.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $step = $input->getOption('step') !== null ? (int)$input->getOption('step') : null;
        $batch = $input->getOption('batch') !== null ? (int)$input->getOption('batch') : null;

        $results = $this->runner->rollback($step, $batch);

        if ($results === []) {
            $io->success('Nothing to roll back.');

            return Command::SUCCESS;
        }

        foreach ($results as $result) {
            $io->writeln($result->success
                ? sprintf('  <fg=green>REVERTED</>  %s (%s)', $result->identifier, $result->description)
                : sprintf(
                    '  <fg=red>FAIL</>  %s (%s): %s',
                    $result->identifier,
                    $result->description,
                    $result->errorMessage ?? '',
                ));
        }

        $failed = array_filter($results, static fn ($result) => !$result->success);

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
