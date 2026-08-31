<?php

declare(strict_types = 1);

namespace App\Audit;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

interface AuditLoggerInterface
{
    public const string ACTION_VIEW = 'view';

    public const string ACTION_DOWNLOAD = 'download';

    public const string ACTION_DELETE = 'delete';

    public const string ACTION_KEY_CHANGE = 'key_change';

    public const string ACTION_PURGE = 'purge';

    public const string RESOURCE_CATEGORY = 'category';

    public const string RESOURCE_ITEM = 'item';

    /**
     * Fire-and-forget — a failure to write an audit row is logged (see the
     * implementation), never thrown, so audit logging can't itself break the
     * action it's recording. $user is null for actions with no logged-in
     * actor (a GC purge, or a Part 9 public-link download).
     */
    public function log(string $action, string $resourceType, int $resourceId, ?User $user, ?Request $request): void;
}
