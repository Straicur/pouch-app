<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Part 7: access keys. Both columns are plain nullable bcrypt hashes (same
 * shape as User::$password, via AccessKeyHasher) — null means "no key of its
 * own", not "unprotected" (see CategoryRepository::findEffectiveKeyHolder()
 * for category-key inheritance).
 */
final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'category.access_key_hash, item.access_key_hash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD access_key_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD access_key_hash VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP access_key_hash');
        $this->addSql('ALTER TABLE category DROP access_key_hash');
    }
}
