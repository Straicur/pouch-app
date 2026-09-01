<?php

declare(strict_types = 1);

namespace App\Security\Voter;

use App\ControllerHelper\Enum\UserRole;
use App\Entity\Tag;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

/**
 * Gates tag *management* (the create/rename/delete CRUD and the "all tags"
 * listing that feeds it) — separate from the read-only, ROLE_GUEST-level
 * GET /api/tags used for item filtering/autocomplete (gated by ItemVoter::VIEW
 * instead, unchanged). Same role thresholds as CategoryVoter: a guest
 * (access-key-only visitor) can see and filter by tags, but only an actual
 * account manages them.
 *
 * @extends Voter<string, Tag|null>
 */
final class TagVoter extends Voter
{
    public const string VIEW = 'TAG_VIEW';

    public const string CREATE = 'TAG_CREATE';

    public const string RENAME = 'TAG_RENAME';

    public const string DELETE = 'TAG_DELETE';

    private const array ATTRIBUTES = [self::VIEW, self::CREATE, self::RENAME, self::DELETE];

    public function __construct(
        private readonly Security $security,
    ) {}

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (false === in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        return null === $subject || $subject instanceof Tag;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::VIEW, self::CREATE, self::RENAME => $this->security->isGranted(UserRole::USER->value),
            self::DELETE => $this->security->isGranted(UserRole::ADMIN->value),
            default      => false,
        };
    }
}
