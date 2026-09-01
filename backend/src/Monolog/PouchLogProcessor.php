<?php

declare(strict_types = 1);

namespace App\Monolog;

use App\Services\Pouch\CurrentPouchResolverInterface;
use LogicException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Override;

/**
 * Tags every log record with the current request's pouch id, so a call site
 * doesn't have to resolve and pass it through itself — autoconfigured as a
 * `monolog.processor` (services.yaml's `autoconfigure: true` picks up
 * ProcessorInterface implementations on its own). Silently a no-op outside
 * an authenticated HTTP request (console command, Messenger worker, a
 * request with no session) — there's no "current pouch" to tag there.
 */
final readonly class PouchLogProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentPouchResolverInterface $currentPouchResolver,
    ) {}

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $pouchId = $this->currentPouchResolver->resolve()->getId();
        } catch (LogicException) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'pouchId' => $pouchId]);
    }
}
