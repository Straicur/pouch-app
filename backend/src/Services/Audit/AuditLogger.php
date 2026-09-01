<?php

declare(strict_types = 1);

namespace App\Services\Audit;

use App\Entity\AuditLog;
use App\Entity\Pouch;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function sprintf;

final readonly class AuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function log(string $action, string $resourceType, int $resourceId, ?User $user, ?Request $request, ?Pouch $pouch = null): void
    {
        try {
            $this->auditLogRepository->save(new AuditLog(
                action: $action,
                resourceType: $resourceType,
                resourceId: $resourceId,
                user: $user,
                ip: $request?->getClientIp(),
                pouch: $pouch,
            ));
        } catch (Throwable $exception) {
            // Never let audit logging itself fail the action it's recording.
            $this->logger->error(sprintf(
                'Failed to write audit log entry (%s %s #%d): %s',
                $action,
                $resourceType,
                $resourceId,
                $exception->getMessage(),
            ));
        }
    }
}
