<?php

declare(strict_types = 1);

namespace App\Command\Storage;

use App\Exception\StorageException;
use App\Storage\StorageServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Manual round-trip check for Part 1 of the roadmap — pairs with
 * app:storage:upload and app:storage:download. See backend/README.md.
 */
#[AsCommand(
    name: 'app:storage:delete',
    description: 'Delete a key from the item storage bucket.',
)]
class StorageDeleteCommand extends Command
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Key (path) in the bucket to delete');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $key */
        $key = $input->getArgument('key');

        try {
            $this->storageService->delete($key);
        } catch (StorageException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Deleted "%s". Confirm it is gone from the MinIO console at :9001.', $key));

        return Command::SUCCESS;
    }
}
