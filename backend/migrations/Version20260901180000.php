<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `gc_run_log.pouch_id`/`audit_log.pouch_id` FKs get `ON DELETE SET NULL` —
 * an admin's self-service "delete my whole pouch" needs to actually remove
 * the `pouch` row, and both of these are append-only history that survives
 * the pouch it happened in (null already means "no single owning pouch",
 * see Version20260901160000) rather than being deleted alongside it.
 * Without this, deleting a pouch with any history at all would fail outright
 * on the FK constraint (NOT DEFERRABLE, no ON DELETE clause = RESTRICT).
 */
final class Version20260901180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'gc_run_log.pouch_id + audit_log.pouch_id FKs: ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gc_run_log DROP CONSTRAINT FK_gc_run_log_pouch');
        $this->addSql('ALTER TABLE gc_run_log ADD CONSTRAINT FK_gc_run_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_audit_log_pouch');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_audit_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_audit_log_pouch');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_audit_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE gc_run_log DROP CONSTRAINT FK_gc_run_log_pouch');
        $this->addSql('ALTER TABLE gc_run_log ADD CONSTRAINT FK_gc_run_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');
    }
}
