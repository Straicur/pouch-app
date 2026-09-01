<?php

declare(strict_types = 1);

namespace App\Monolog;

use App\Services\Pouch\CurrentPouchResolverInterface;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Override;
use Throwable;

/**
 * Tags every log record with the current request's pouch id, so a call site
 * doesn't have to resolve and pass it through itself — autoconfigured as a
 * `monolog.processor` (services.yaml's `autoconfigure: true` picks up
 * ProcessorInterface implementations on its own). Silently a no-op outside
 * an authenticated HTTP request (console command, Messenger worker, a
 * request with no session) — there's no "current pouch" to tag there.
 *
 * Catches Throwable, not just resolve()'s documented LogicException — a
 * request that deletes its own account/pouch (AccountController) still
 * holds a Security token pointing at the now-removed User/Pouch for any log
 * line between that deletion and the token being cleared; touching that
 * stale entity graph can throw a plain PHP Error (an uninitialized typed
 * property on a Doctrine proxy that can no longer load), not a
 * LogicException. A log-enrichment processor must never be the thing that
 * turns a successful request into a 500.
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
        } catch (Throwable) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'pouchId' => $pouchId]);
    }
}
