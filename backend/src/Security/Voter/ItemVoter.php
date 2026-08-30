<?php

declare(strict_types = 1);

namespace App\Security\Voter;

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

    private const array ATTRIBUTES = [self::VIEW, self::DOWNLOAD, self::CREATE, self::EDIT, self::DELETE];

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
            self::VIEW, self::DOWNLOAD => $this->security->isGranted('ROLE_GUEST'),
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted('ROLE_USER'),
            default => false,
        };
    }
}
