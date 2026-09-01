<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Część 17: adds item.url to search_vector's weight D (alongside
 * extracted_text/page_description) — a bare domain/URL fragment typed into
 * search now matches even when the scraped page title/description doesn't
 * happen to repeat it. Hand-written, same as Version20260831190000 and
 * Version20260830210000: Postgres has no ALTER for a generated column's
 * expression, only drop + re-add.
 */
final class Version20260901200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Część 17: item.url added to search_vector weight D';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_item_search_vector');
        $this->addSql('ALTER TABLE item DROP search_vector');

        $this->addSql(<<<'SQL'
            ALTER TABLE item ADD search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(name, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(page_title, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(note_content, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(extracted_text, '') || ' ' || coalesce(page_description, '') || ' ' || coalesce(url, '')), 'D')
            ) STORED
            SQL);
        $this->addSql('CREATE INDEX idx_item_search_vector ON item USING GIN (search_vector)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_item_search_vector');
        $this->addSql('ALTER TABLE item DROP search_vector');

        $this->addSql(<<<'SQL'
            ALTER TABLE item ADD search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(name, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(page_title, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(note_content, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(extracted_text, '') || ' ' || coalesce(page_description, '')), 'D')
            ) STORED
            SQL);
        $this->addSql('CREATE INDEX idx_item_search_vector ON item USING GIN (search_vector)');
    }
}
