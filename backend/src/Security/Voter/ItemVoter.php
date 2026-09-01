<?php

declare(strict_types = 1);

namespace App\Security\Voter;

use App\ControllerHelper\Enum\UserRole;
use App\Entity\Item;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

/**
 * Per product doc: guest can view/open items, user has full CRUD on items
 * (unlike categories, item deletion isn't admin-only — there's no per-item
 * ownership yet to distinguish "your item" from "someone else's").
 *
 * @extends Voter<string, Item|null>
 */
final class ItemVoter extends Voter
{
    public const string VIEW = 'ITEM_VIEW';

    public const string DOWNLOAD = 'ITEM_DOWNLOAD';

    public const string CREATE = 'ITEM_CREATE';

    public const string EDIT = 'ITEM_EDIT';

    public const string DELETE = 'ITEM_DELETE';

    /** Set/change/remove the item's own access key (Part 7). */
    public const string MANAGE_KEY = 'ITEM_MANAGE_KEY';

    /** Submit a key to unlock a protected item (Part 7) — same role floor as VIEW. */
    public const string UNLOCK = 'ITEM_UNLOCK';

    private const array ATTRIBUTES = [self::VIEW, self::DOWNLOAD, self::CREATE, self::EDIT, self::DELETE, self::MANAGE_KEY, self::UNLOCK];

    public function __construct(
        private readonly Security $security,
    ) {}

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (false === in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        return null === $subject || $subject instanceof Item;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::VIEW, self::DOWNLOAD, self::UNLOCK => $this->security->isGranted(UserRole::GUEST->value),
            self::CREATE, self::EDIT, self::DELETE, self::MANAGE_KEY => $this->security->isGranted(UserRole::USER->value),
            default => false,
        };
    }
}
