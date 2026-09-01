<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `item.pouch_id` — denormalized from `item.category_id -> category.pouch_id`
 * (an item never changes category after creation, so this never drifts once
 * backfilled). Hand-written, not `make:migration`'s raw diff: a NOT NULL FK
 * on a table with existing rows needs a backfill in between.
 *
 * Content-hash dedup was global (one Postgres partial unique index across
 * every pouch) — replaced with a composite (pouch_id, content_hash) index so
 * two pouches can independently hold the same file, and the app-level
 * duplicate check (ItemService) can be scoped the same way.
 */
final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'item.pouch_id (NOT NULL, backfilled from category.pouch_id) + content_hash uniqueness scoped per pouch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item ADD pouch_id INT DEFAULT NULL');
        $this->addSql('UPDATE item SET pouch_id = (SELECT c.pouch_id FROM category c WHERE c.category_id = item.category_id)');
        $this->addSql('ALTER TABLE item ALTER pouch_id SET NOT NULL');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_item_pouch FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_item_pouch_id ON item (pouch_id)');

        $this->addSql('DROP INDEX uniq_item_content_hash_active');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_content_hash_active ON item (pouch_id, content_hash) WHERE (trashed_at IS NULL AND content_hash IS NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_item_content_hash_active');
        $this->addSql('CREATE UNIQUE INDEX uniq_item_content_hash_active ON item (content_hash) WHERE (trashed_at IS NULL AND content_hash IS NOT NULL)');

        $this->addSql('ALTER TABLE item DROP CONSTRAINT FK_item_pouch');
        $this->addSql('DROP INDEX idx_item_pouch_id');
        $this->addSql('ALTER TABLE item DROP pouch_id');
    }
}
