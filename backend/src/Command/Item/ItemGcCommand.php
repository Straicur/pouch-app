<?php

declare(strict_types = 1);

namespace App\Command\Item;

use App\Entity\GcRunLog;
use App\Item\ItemGarbageCollectorInterface;
use DateInterval;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function sprintf;

/**
 * "Run GC Now" from the CLI, tagged as the "cron" trigger in GcRunLog — the
 * admin dashboard's own "Run GC Now" (Part 10) goes through the exact same
 * ItemGarbageCollectorInterface::run(), tagged "manual" instead.
 * --retention-days is there for manual dev testing on a shortened trash
 * window (see roadmap Part 3's manual test), not for prod use.
 */
#[AsCommand(
    name: 'app:item:gc',
    description: 'Move overdue-TTL items to the trash, then purge anything that has been trashed long enough.',
)]
class ItemGcCommand extends Command
{
    public function __construct(
        private readonly ItemGarbageCollectorInterface $itemGarbageCollector,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'retention-days',
            null,
            InputOption::VALUE_REQUIRED,
            'Override the trash retention period (days) before permanent deletion — default 7',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retentionDays = $input->getOption('retention-days');
        $retention = is_string($retentionDays) ? new DateInterval(sprintf('P%dD', (int) $retentionDays)) : null;

        $runLog = $this->itemGarbageCollector->run(trigger: GcRunLog::TRIGGER_CRON, retention: $retention);

        $io->success(sprintf(
            'Moved %d item(s) to the trash. Purged %d item(s) from the trash.',
            $runLog->getExpiredCount(),
            $runLog->getPurgedCount(),
        ));

        return Command::SUCCESS;
    }
}
