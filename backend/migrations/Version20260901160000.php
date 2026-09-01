<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `gc_run_log.pouch_id`/`audit_log.pouch_id`, both nullable — a manual GC run
 * or a logged action can now be scoped to one pouch (admin's per-pouch view,
 * Krok 3); null means "every pouch" (the cron sweep, or an action with no
 * single owning pouch).
 */
final class Version20260901160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'gc_run_log.pouch_id + audit_log.pouch_id, both nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gc_run_log ADD pouch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE gc_run_log ADD CONSTRAINT FK_gc_run_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_gc_run_log_pouch_id ON gc_run_log (pouch_id)');

        $this->addSql('ALTER TABLE audit_log ADD pouch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_audit_log_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_audit_log_pouch_id ON audit_log (pouch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_audit_log_pouch');
        $this->addSql('DROP INDEX idx_audit_log_pouch_id');
        $this->addSql('ALTER TABLE audit_log DROP pouch_id');

        $this->addSql('ALTER TABLE gc_run_log DROP CONSTRAINT FK_gc_run_log_pouch');
        $this->addSql('DROP INDEX idx_gc_run_log_pouch_id');
        $this->addSql('ALTER TABLE gc_run_log DROP pouch_id');
    }
}
