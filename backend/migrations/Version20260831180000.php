<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Post-Part-7-review fix: a signed access grant (AccessKeyGuard) used to be
 * scoped only to "this resource, until this timestamp" — resetting a
 * compromised key didn't invalidate grants already handed out for it, since
 * nothing about the signed string changed when the hash did. This column
 * bumps every time Category/Item::setAccessKeyHash() runs (see those
 * entities), and is folded into the signed resource string
 * (AccessKeyResource) — a grant signed against version N stops matching the
 * moment the key changes to version N+1, automatically, with no revocation
 * list needed.
 */
final class Version20260831180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'category.access_key_version, item.access_key_version — invalidate grants on key change';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD access_key_version INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE item ADD access_key_version INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP access_key_version');
        $this->addSql('ALTER TABLE category DROP access_key_version');
    }
}
