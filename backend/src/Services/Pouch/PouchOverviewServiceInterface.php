<?php

declare(strict_types = 1);

namespace App\Services\Pouch;

interface PouchOverviewServiceInterface
{
    /**
     * Every pouch with its user/category/active-item counts — admin's
     * "przegląd pouchy (nazwa, ile ma userów/kategorii/itemów)".
     *
     * @return list<PouchOverview>
     */
    public function list(): array;
}
