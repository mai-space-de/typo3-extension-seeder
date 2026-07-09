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

#[AsCommand(name: 'mai-seeder:migrate:reset', description: 'Roll back all executed migrations.')]
final class MigrateResetCommand extends Command
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

        if (!$input->getOption('force') && !$io->confirm('This rolls back every executed migration. Continue?', false)) {
            $io->warning('Command cancelled.');

            return Command::SUCCESS;
        }

        $results = $this->runner->reset();
        $failed = array_filter($results, static fn ($result) => !$result->success);

        $io->success(sprintf('%d migration(s) rolled back.', count($results) - count($failed)));

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
