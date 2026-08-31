<?php

declare(strict_types = 1);

namespace App\Command\Storage;

use App\Exception\StorageException;
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

/**
 * Manual round-trip check for Part 1 of the roadmap — pairs with
 * app:storage:download and app:storage:delete. See backend/README.md.
 */
#[AsCommand(
    name: 'app:storage:upload',
    description: 'Upload a local file to the item storage bucket (streaming, no in-memory buffering).',
)]
class StorageUploadCommand extends Command
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
            ->addArgument('path', InputArgument::REQUIRED, 'Path to the local file to upload')
            ->addArgument('key', InputArgument::REQUIRED, 'Destination key (path) in the bucket');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $path */
        $path = $input->getArgument('path');
        /** @var string $key */
        $key = $input->getArgument('key');

        $stream = fopen($path, 'r');
        if (false === is_resource($stream)) {
            $io->error(sprintf('Could not open "%s" for reading.', $path));

            return Command::FAILURE;
        }

        try {
            $this->storageService->upload($key, $stream);
        } catch (StorageException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $io->success(sprintf('Uploaded "%s" to "%s". Check the MinIO console at :9001.', $path, $key));

        return Command::SUCCESS;
    }
}
