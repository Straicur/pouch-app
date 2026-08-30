<?php

declare(strict_types = 1);

namespace App\Security\Voter;

use App\Entity\Category;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

/**
 * Purely role-based for now (see security.yaml's role_hierarchy): guest reads,
 * user creates/renames/moves, admin also deletes. $subject is accepted (and
 * currently ignored) so per-category ACL — Part 7's access keys — can slot in
 * later without changing every call site.
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

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::RENAME, self::MOVE, self::DELETE];

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
            self::VIEW => $this->security->isGranted('ROLE_GUEST'),
            self::CREATE, self::RENAME, self::MOVE => $this->security->isGranted('ROLE_USER'),
            self::DELETE => $this->security->isGranted('ROLE_ADMIN'),
            default      => false,
        };
    }
}
