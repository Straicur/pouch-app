<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Część 17: pg_trgm, for a typo-tolerant fallback when the normal prefix
 * `to_tsquery` search returns nothing (see ItemRepository::searchMatchingIds()).
 * Hand-written — `CREATE EXTENSION`/a GIN index on a raw expression aren't
 * things Doctrine's schema diff can generate at all.
 *
 * The indexed expression matches search_vector's own field list/order (see
 * Version20260901200000) so the fallback searches exactly the same text the
 * primary search does, just via trigram similarity instead of a tsquery.
 */
final class Version20260901210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Część 17: pg_trgm extension + trigram GIN index for typo-tolerant search fallback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_item_trgm ON item USING GIN (
                (coalesce(name, '') || ' ' || coalesce(page_title, '') || ' ' || coalesce(note_content, '') || ' ' || coalesce(extracted_text, '') || ' ' || coalesce(page_description, '') || ' ' || coalesce(url, ''))
                gin_trgm_ops
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_item_trgm');
        $this->addSql('DROP EXTENSION IF EXISTS pg_trgm');
    }
}
