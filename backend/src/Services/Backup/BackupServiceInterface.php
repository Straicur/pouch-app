<?php

declare(strict_types = 1);

namespace App\Services\Backup;

use App\ExceptionManagement\Exceptions\Command\BackupException;
use App\Services\Backup\ValueObject\BackupRestoreTestResult;
use App\Services\Backup\ValueObject\BackupRunResult;

interface BackupServiceInterface
{
    /**
     * A full `pg_dump` of the database plus a mirror of every object in the
     * storage bucket, into a new timestamped directory — then prunes
     * anything past the retention count. Unlike CategoryExportServiceInterface
     * (an app-level, per-category ZIP re-derived from entities), this is a
     * schema/storage-level backup: it also catches anything the ORM layer
     * doesn't know about (orphaned storage objects, non-Item tables).
     *
     * @throws BackupException
     */
    public function run(): BackupRunResult;

    /**
     * Proves the *latest* backup actually restores, not just that it exists:
     * restores its database dump into a throwaway database (created and
     * dropped within this one call) and compares row counts against the live
     * database for a handful of core tables.
     *
     * @throws BackupException if there is no backup to test at all
     */
    public function restoreTest(): BackupRestoreTestResult;
}
