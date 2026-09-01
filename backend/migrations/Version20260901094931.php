<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Część 17: `tag.pouch_id` (NOT NULL) — tags scoped per Pouch instead of
 * globally unique by name. Hand-written, not the raw `make:migration` diff:
 * a NOT NULL FK on a table with existing rows needs a backfill in between
 * (same shape as Version20260831190537.php for category/user), and this one
 * additionally needs to resolve which pouch each existing tag belongs to
 * from the items it's attached to (a tag has no pouch of its own yet).
 *
 * A tag used by items from more than one pouch can't be backfilled safely —
 * that would mean picking one pouch arbitrarily and silently stripping the
 * tag from every other pouch's items. The DO block below raises loudly
 * instead of guessing; a dev-DB check before writing this migration found
 * zero such tags, so this is a safety net, not an expected path. A tag
 * attached to no live item is dropped instead of backfilled (already
 * excluded from every "tags in use" listing anyway — see
 * TagRepository::findInUseOrderedByName()).
 *
 * The auto-generated diff this was based on also wanted to rename several
 * unrelated indexes and drop/recreate item.search_vector — that's Doctrine's
 * schema comparator not understanding a DB-generated column (unmapped in
 * the Item entity on purpose), not real drift, so it's left out (same as
 * Version20260831190537.php's own docblock notes).
 */
final class Version20260901094931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Część 17: tag.pouch_id (NOT NULL), backfilled from each tag\'s items; unique constraint moved from name alone to (name, pouch_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag ADD pouch_id INT DEFAULT NULL');

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                cross_pouch_tags INT;
            BEGIN
                SELECT COUNT(*) INTO cross_pouch_tags FROM (
                    SELECT it.tag_id
                    FROM item_tag it
                    JOIN item i ON i.item_id = it.item_id
                    GROUP BY it.tag_id
                    HAVING COUNT(DISTINCT i.pouch_id) > 1
                ) x;
                IF cross_pouch_tags > 0 THEN
                    RAISE EXCEPTION 'Migration assumes every tag belongs to items in exactly one pouch, but % tag(s) span more than one — manual intervention needed before this migration can run', cross_pouch_tags;
                END IF;
            END $$;
            SQL);

        $this->addSql('DELETE FROM tag WHERE tag_id NOT IN (SELECT DISTINCT tag_id FROM item_tag)');

        $this->addSql(<<<'SQL'
            UPDATE tag t SET pouch_id = sub.pouch_id
            FROM (
                SELECT it.tag_id, MIN(i.pouch_id) AS pouch_id
                FROM item_tag it
                JOIN item i ON i.item_id = it.item_id
                GROUP BY it.tag_id
            ) sub
            WHERE t.tag_id = sub.tag_id
            SQL);

        $this->addSql('ALTER TABLE tag ALTER pouch_id SET NOT NULL');

        $this->addSql('DROP INDEX uniq_tag_name');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B783566320D4 FOREIGN KEY (pouch_id) REFERENCES pouch (pouch_id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_389B783566320D4 ON tag (pouch_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_name_pouch ON tag (name, pouch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_389B783566320D4');
        $this->addSql('DROP INDEX IDX_389B783566320D4');
        $this->addSql('DROP INDEX uniq_tag_name_pouch');
        $this->addSql('ALTER TABLE tag DROP pouch_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_name ON tag (name)');
    }
}
