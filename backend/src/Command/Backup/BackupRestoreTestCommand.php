<?php

declare(strict_types = 1);

namespace App\Command\Backup;

use App\Services\Backup\BackupServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup:restore-test',
    description: 'Restores the latest backup into a throwaway database and verifies its row counts, then drops it.',
)]
class BackupRestoreTestCommand extends Command
{
    public function __construct(
        private readonly BackupServiceInterface $backupService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->backupService->restoreTest();

        if (!$result->ok) {
            $io->error($result->detail);

            return Command::FAILURE;
        }

        $io->success($result->detail);

        return Command::SUCCESS;
    }
}
