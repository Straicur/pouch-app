<?php

declare(strict_types = 1);

namespace App\Services\Audit;

use App\Entity\Pouch;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

interface AuditLoggerInterface
{
    public const string ACTION_VIEW = 'view';

    public const string ACTION_DOWNLOAD = 'download';

    public const string ACTION_DELETE = 'delete';

    public const string ACTION_KEY_CHANGE = 'key_change';

    public const string ACTION_PURGE = 'purge';

    public const string ACTION_RESTORE = 'restore';

    public const string RESOURCE_CATEGORY = 'category';

    public const string RESOURCE_ITEM = 'item';

    public const string RESOURCE_USER = 'user';

    public const string RESOURCE_TAG = 'tag';

    /**
     * Fire-and-forget — a failure to write an audit row is logged (see the
     * implementation), never thrown, so audit logging can't itself break the
     * action it's recording. $user is null for actions with no logged-in
     * actor (a GC purge, or a Part 9 public-link download). $pouch is the
     * *resource's* pouch (not necessarily the actor's — an admin acting
     * across pouches logs the target's), letting the admin panel filter the
     * log per pouch; null for an action with no single owning pouch.
     */
    public function log(string $action, string $resourceType, int $resourceId, ?User $user, ?Request $request, ?Pouch $pouch = null): void;
}
