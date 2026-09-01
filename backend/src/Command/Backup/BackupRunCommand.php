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

use function sprintf;

#[AsCommand(
    name: 'app:backup:run',
    description: 'Full pg_dump of the database plus a mirror of the storage bucket, into a new timestamped directory.',
)]
class BackupRunCommand extends Command
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

        $result = $this->backupService->run();

        $io->success(sprintf(
            '%s — database dump %.1f MB, %d storage file(s) (%.1f MB). Pruned %d old backup(s).',
            $result->backupDir,
            $result->databaseDumpBytes / 1_048_576,
            $result->storageFileCount,
            $result->storageBytes / 1_048_576,
            $result->prunedBackupCount,
        ));

        return Command::SUCCESS;
    }
}
