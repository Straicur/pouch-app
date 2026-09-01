<?php

declare(strict_types = 1);

namespace App\Security\Voter;

use App\ControllerHelper\Enum\UserRole;
use App\Entity\Category;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

/**
 * Purely role-based (see security.yaml's role_hierarchy): guest reads, user
 * creates/renames/moves/manages its own key, admin also deletes. $subject is
 * accepted (and currently ignored) so per-category ACL could use it later.
 *
 * This only answers "does your role let you attempt this" — whether you
 * actually *know* the access key (Part 7) is a separate question, checked by
 * AccessKeyGuard, not this voter: MANAGE_KEY gates who may set/change/remove
 * a key, UNLOCK gates who may attempt to submit one.
 *
 * @extends Voter<string, Category|null>
 */
final class CategoryVoter extends Voter
{
    public const string VIEW = 'CATEGORY_VIEW';

    public const string CREATE = 'CATEGORY_CREATE';

    public const string RENAME = 'CATEGORY_RENAME';

    public const string MOVE = 'CATEGORY_MOVE';

    public const string DELETE = 'CATEGORY_DELETE';

    /** Set/change/remove the category's own access key (Part 7). */
    public const string MANAGE_KEY = 'CATEGORY_MANAGE_KEY';

    /** Submit a key to unlock a protected category (Part 7) — same role floor as VIEW. */
    public const string UNLOCK = 'CATEGORY_UNLOCK';

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::RENAME, self::MOVE, self::DELETE, self::MANAGE_KEY, self::UNLOCK];

    public function __construct(
        private readonly Security $security,
    ) {}

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (false === in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        return null === $subject || $subject instanceof Category;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Delegated to isGranted() (not $token->getRoleNames()) so role_hierarchy
        // applies — an admin has ROLE_USER/ROLE_GUEST too, not just ROLE_ADMIN.
        return match ($attribute) {
            self::VIEW, self::UNLOCK => $this->security->isGranted(UserRole::GUEST->value),
            self::CREATE, self::RENAME, self::MOVE, self::MANAGE_KEY => $this->security->isGranted(UserRole::USER->value),
            self::DELETE => $this->security->isGranted(UserRole::ADMIN->value),
            default      => false,
        };
    }
}
