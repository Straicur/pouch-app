<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Weights item.search_vector by field (name > page_title > note_content >
 * extracted_text/page_description) instead of treating every field equally —
 * see ItemRepository::searchMatchingIds()'s ts_rank() call, which already
 * reads a tsvector's setweight() labels automatically. Hand-written, same as
 * Version20260830210000 which first added this GENERATED column: Postgres
 * has no ALTER for a generated column's expression, only drop + re-add.
 */
final class Version20260831190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Weighted item.search_vector (name > page_title > note_content > extracted_text/page_description)';
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
                setweight(to_tsvector('simple', coalesce(extracted_text, '') || ' ' || coalesce(page_description, '')), 'D')
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
                to_tsvector('simple',
                    coalesce(name, '') || ' ' ||
                    coalesce(note_content, '') || ' ' ||
                    coalesce(extracted_text, '') || ' ' ||
                    coalesce(page_title, '') || ' ' ||
                    coalesce(page_description, '')
                )
            ) STORED
            SQL);
        $this->addSql('CREATE INDEX idx_item_search_vector ON item USING GIN (search_vector)');
    }
}
