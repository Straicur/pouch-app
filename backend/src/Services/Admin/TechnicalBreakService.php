<?php

declare(strict_types = 1);

namespace App\Services\Admin;

use App\Entity\TechnicalBreak;
use App\Repository\TechnicalBreakRepository;
use Override;

final readonly class TechnicalBreakService implements TechnicalBreakServiceInterface
{
    public function __construct(
        private TechnicalBreakRepository $technicalBreakRepository,
    ) {}

    #[Override]
    public function getActive(): ?TechnicalBreak
    {
        return $this->technicalBreakRepository->findActive();
    }

    #[Override]
    public function enable(?string $message): TechnicalBreak
    {
        $active = $this->technicalBreakRepository->findActive();

        if (null === $active) {
            $active = new TechnicalBreak($message);
        } else {
            $active->setMessage($message);
        }

        $this->technicalBreakRepository->save($active);

        return $active;
    }

    #[Override]
    public function disable(): ?TechnicalBreak
    {
        $active = $this->technicalBreakRepository->findActive();
        if (null === $active) {
            return null;
        }

        $active->deactivate();
        $this->technicalBreakRepository->save($active);

        return $active;
    }
}
