<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519171915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job ADD sourceFileReference VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_short_name ON family_info');
        $this->addSql('DROP INDEX idx_long_name ON family_info');
        $this->addSql('CREATE INDEX family_index ON family_info (family)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job DROP sourceFileReference');
        $this->addSql('DROP INDEX family_index ON family_info');
        $this->addSql('CREATE INDEX idx_short_name ON family_info (short_name)');
        $this->addSql('CREATE INDEX idx_long_name ON family_info (long_name)');
    }
}
