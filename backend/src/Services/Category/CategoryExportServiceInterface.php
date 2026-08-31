<?php

declare(strict_types = 1);

namespace App\Services\Category;

use App\ExceptionManagement\Exceptions\ApiException\NotFoundException\NotFoundException;
use Symfony\Component\HttpFoundation\Request;

interface CategoryExportServiceInterface
{
    /**
     * Builds a ZIP archive of $categoryId and its full subtree — files/photos
     * as their original bytes, notes as `.md`, URLs as a small `.txt`
     * summary — preserving the category tree as folders. An item (or a whole
     * locked subcategory's items) $request doesn't currently have a valid
     * access-key grant for (Part 7) is silently left out rather than failing
     * the export, same as GET /api/items does — category *names* aren't
     * access-key-gated anywhere else in the app, so the folder structure
     * itself is never hidden, only file content.
     *
     * @return string absolute path to the finished archive on local disk —
     *                the caller streams it out and is responsible for
     *                deleting it once done
     *
     * @throws NotFoundException if $categoryId doesn't exist
     */
    public function buildZip(int $categoryId, Request $request): string;

    /**
     * Part 10: "eksport/backup całości jako ZIP" — every root category (and
     * its full subtree) in one archive.
     *
     * Post-review fix: admin-only (see AdminController::backup()) and
     * deliberately does NOT apply Part 7 locks the way buildZip() does — a
     * full disaster-recovery backup by an already-authenticated admin is a
     * different action than ordinary browsing, and locks exist to gate the
     * latter (same reasoning as AccessKeyController's admin key-reset
     * bypass, which doesn't require proving the current key either).
     * Explicit, intentional policy, not an oversight: an admin backup always
     * includes everything.
     *
     * @return string absolute path to the finished archive on local disk —
     *                the caller streams it out and is responsible for
     *                deleting it once done
     */
    public function buildFullBackupZip(Request $request): string;
}
