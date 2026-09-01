<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

use App\Entity\Pouch;
use App\Repository\PouchRepository;
use Override;

use function array_map;

class PouchOverviewService implements PouchOverviewServiceInterface
{
    public function __construct(
        private readonly PouchRepository $pouchRepository,
    ) {}

    #[Override]
    public function list(): array
    {
        $counts = $this->pouchRepository->countsByPouchId();

        return array_map(
            function (Pouch $pouch) use ($counts): PouchOverview {
                $forPouch = $counts[$pouch->getId()] ?? ['userCount' => 0, 'categoryCount' => 0, 'itemCount' => 0];

                return new PouchOverview(
                    pouch: $pouch,
                    userCount: $forPouch['userCount'],
                    categoryCount: $forPouch['categoryCount'],
                    itemCount: $forPouch['itemCount'],
                );
            },
            $this->pouchRepository->findAllOrderedByName(),
        );
    }
}
