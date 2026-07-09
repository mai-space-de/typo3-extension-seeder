<?php

declare(strict_types=1);

namespace Maispace\MaiSeeder\Command;

use Maispace\MaiSeeder\Migration\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mai-seeder:migrate:status', description: 'List all discovered migrations and their execution status.')]
final class MigrateStatusCommand extends Command
{
    public function __construct(private readonly MigrationRunner $runner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statuses = $this->runner->status();

        if ($statuses === []) {
            $io->note("No migrations found in any active extension's Classes/Migrations/ directory.");

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($statuses as $status) {
            $state = match (true) {
                !$status->executed => '<comment>pending</comment>',
                $status->success === false => '<fg=red>failed</>',
                default => '<fg=green>executed</>',
            };

            $rows[] = [
                $status->identifier,
                $status->description,
                $state,
                $status->batch !== null ? (string)$status->batch : '-',
                $status->executedAt?->format('Y-m-d H:i:s') ?? '-',
                $status->reversible ? 'yes' : 'no',
            ];
        }

        $io->table(['Identifier', 'Description', 'Status', 'Batch', 'Executed at', 'Reversible'], $rows);

        return Command::SUCCESS;
    }
}
