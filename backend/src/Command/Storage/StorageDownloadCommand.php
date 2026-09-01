<?php

declare(strict_types = 1);

namespace App\Command\Storage;

use App\ExceptionManagement\Exceptions\Command\StorageException;
use App\Services\Storage\StorageServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function fclose;
use function fopen;
use function is_resource;
use function sprintf;
use function stream_copy_to_stream;

/**
 * Manual round-trip check for Part 1 of the roadmap — pairs with
 * app:storage:upload and app:storage:delete. See backend/README.md.
 */
#[AsCommand(
    name: 'app:storage:download',
    description: 'Download a key from the item storage bucket to a local file (streaming, no in-memory buffering).',
)]
class StorageDownloadCommand extends Command
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'Key (path) in the bucket to download')
            ->addArgument('destination', InputArgument::REQUIRED, 'Local path to write the file to');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $key */
        $key = $input->getArgument('key');
        /** @var string $destination */
        $destination = $input->getArgument('destination');

        $destinationStream = fopen($destination, 'w');
        if (false === is_resource($destinationStream)) {
            $io->error(sprintf('Could not open "%s" for writing.', $destination));

            return Command::FAILURE;
        }

        try {
            $sourceStream = $this->storageService->download($key);
            stream_copy_to_stream($sourceStream, $destinationStream);
            fclose($sourceStream);
        } catch (StorageException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            fclose($destinationStream);
        }

        $io->success(sprintf('Downloaded "%s" to "%s".', $key, $destination));

        return Command::SUCCESS;
    }
}
