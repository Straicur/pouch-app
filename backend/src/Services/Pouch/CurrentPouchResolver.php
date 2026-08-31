<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Pouch;
use App\Entity\User;
use LogicException;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Shared by CategoryService and ItemService — both need the same "which
 * pouch does the current request belong to" answer to scope their queries.
 */
final readonly class CurrentPouchResolver implements CurrentPouchResolverInterface
{
    public function __construct(
        private Security $security,
    ) {}

    #[Override]
    public function resolve(): Pouch
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new LogicException('No authenticated User to resolve a Pouch for.');
        }

        return $user->getPouch();
    }
}
