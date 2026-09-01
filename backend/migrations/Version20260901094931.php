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
 * Code-review fix: an earlier version of this migration aborted outright
 * (RAISE EXCEPTION) if a tag was used by items from more than one pouch —
 * but under the previous globally-unique-by-name model, that's the *normal*
 * outcome whenever two different pouches independently tag something with
 * the same word, not a rare edge case. This version splits such a tag
 * instead: the pouch its items appear in most/first (MIN(pouch_id), an
 * arbitrary but deterministic tiebreak) keeps the original row; every other
 * pouch gets its own new tag row with the same name, and the items in that
 * pouch are repointed to it (`tag_pouch_pairs`/`tag_primary_pouch`/
 * `tag_split_map` below). Verified against a synthetic cross-pouch scenario
 * on a scratch database before being written here — see docs/ROADMAP.md's
 * Część 17/18 note.
 *
 * A tag attached to no live item at all is dropped instead of backfilled —
 * it has no items to infer a pouch from, and it was already excluded from
 * every "tags in use" listing before this migration (see
 * TagRepository::findInUseOrderedByName()). It won't show up as
 * pre-existing "unused" on the new tag-management page (GET /api/tags/all)
 * either way, since that page only lists a pouch's *own* tags going
 * forward — an unused tag from before this migration has no single pouch
 * to belong to in the first place.
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
        return 'Część 17: tag.pouch_id (NOT NULL) — backfilled from each tag\'s items, splitting a tag used across '
            . 'more than one pouch into one row per pouch; unique constraint moved from name alone to (name, pouch_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag ADD pouch_id INT DEFAULT NULL');

        $this->addSql('DELETE FROM tag WHERE tag_id NOT IN (SELECT DISTINCT tag_id FROM item_tag)');

        // Every distinct (tag, pouch) combination a tag's items actually
        // appear in — a tag used only within one pouch has exactly one row
        // here, one used across several has one row per pouch.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE tag_pouch_pairs AS
            SELECT DISTINCT it.tag_id, i.pouch_id
            FROM item_tag it
            JOIN item i ON i.item_id = it.item_id
            SQL);

        // The pouch each tag's original row stays in — MIN(pouch_id) is an
        // arbitrary but deterministic tiebreak among a cross-pouch tag's
        // pouches; every *other* pouch gets a split-off copy below.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE tag_primary_pouch AS
            SELECT tag_id, MIN(pouch_id) AS pouch_id
            FROM tag_pouch_pairs
            GROUP BY tag_id
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE tag t SET pouch_id = tpp.pouch_id
            FROM tag_primary_pouch tpp
            WHERE t.tag_id = tpp.tag_id
            SQL);

        // For every (tag, pouch) pair beyond the primary one: a brand new
        // tag row with the same name in that pouch, and a map from the
        // original tag id + that pouch to the new tag id — tag.name was
        // (still is, at this point in the migration) globally unique, so
        // joining split rows back to their source by (name, pouch_id) can't
        // collide with anything else being inserted in this same statement.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE tag_split_map AS
            WITH extra_pairs AS (
                SELECT tpp.tag_id AS old_tag_id, tpp.pouch_id
                FROM tag_pouch_pairs tpp
                JOIN tag_primary_pouch p ON p.tag_id = tpp.tag_id
                WHERE tpp.pouch_id <> p.pouch_id
            ), inserted AS (
                INSERT INTO tag (name, pouch_id, created_at)
                SELECT t.name, ep.pouch_id, t.created_at
                FROM extra_pairs ep
                JOIN tag t ON t.tag_id = ep.old_tag_id
                RETURNING tag_id, name, pouch_id
            )
            SELECT ep.old_tag_id, ep.pouch_id, ins.tag_id AS new_tag_id
            FROM extra_pairs ep
            JOIN tag t ON t.tag_id = ep.old_tag_id
            JOIN inserted ins ON ins.name = t.name AND ins.pouch_id = ep.pouch_id
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE item_tag it
            SET tag_id = m.new_tag_id
            FROM tag_split_map m, item i
            WHERE it.tag_id = m.old_tag_id
              AND it.item_id = i.item_id
              AND i.pouch_id = m.pouch_id
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

        // Reverses the *schema* (global uniqueness on name alone), not
        // necessarily which pouch each item's tag came from post-split —
        // same trade-off Version20260831190537.php's own down() makes for
        // category/user's pouch. Every split row is merged back onto the
        // lowest-id row sharing its name (arbitrary but deterministic) so
        // the restored unique index on (name) alone doesn't immediately
        // fail on a name that now exists more than once.
        $this->addSql(<<<'SQL'
            UPDATE item_tag it
            SET tag_id = canon.tag_id
            FROM (
                SELECT DISTINCT ON (name) tag_id, name
                FROM tag
                ORDER BY name, tag_id
            ) canon, tag t
            WHERE it.tag_id = t.tag_id AND t.name = canon.name AND t.tag_id <> canon.tag_id
            SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM tag t
            WHERE EXISTS (
                SELECT 1 FROM tag t2 WHERE t2.name = t.name AND t2.tag_id < t.tag_id
            )
            SQL);

        $this->addSql('ALTER TABLE tag DROP pouch_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_name ON tag (name)');
    }
}
