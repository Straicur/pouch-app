<?php

declare(strict_types = 1);

namespace App\ControllerHelper\Enum;

/**
 * GET /api/items' (and its admin counterpart's) pagination defaults/cap —
 * see ItemRepository::findFilteredPage(). DEFAULT keeps a page well under a
 * real "several MB" response even with sizeable note bodies, and lines up
 * with ItemGrid's card layout; MAX is a hard ceiling so `?pageSize=999999`
 * can't be used to route around pagination entirely.
 */
enum ItemListPageSize: int
{
    case DEFAULT = 24;

    case MAX = 200;
}
