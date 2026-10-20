<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616201142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE SiteNotice (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, isActive TINYINT(1) NOT NULL, type VARCHAR(20) NOT NULL, createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', activatedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE PFAM_clans CHANGE pfam_id pfam_id VARCHAR(255) NOT NULL, ADD PRIMARY KEY (pfam_id)');
        $this->addSql('ALTER TABLE family_info CHANGE family family VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE SiteNotice');
        $this->addSql('DROP INDEX `primary` ON PFAM_clans');
        $this->addSql('ALTER TABLE PFAM_clans CHANGE pfam_id pfam_id VARCHAR(24) DEFAULT NULL');
        $this->addSql('ALTER TABLE family_info CHANGE family family VARCHAR(10) NOT NULL');
    }
}
