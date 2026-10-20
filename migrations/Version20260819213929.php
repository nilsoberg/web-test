<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819213929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job DROP referenceDatabase');
        $this->addSql('ALTER TABLE Job ADD quantifySearchType VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE Job ADD metagenome_db_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Job ADD CONSTRAINT FK_C395A618B7371B00 FOREIGN KEY (metagenome_db_id) REFERENCES DatabaseMetadata (id)');
        $this->addSql('CREATE INDEX IDX_C395A618B7371B00 ON Job (metagenome_db_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job DROP quantifySearchType');
        $this->addSql('ALTER TABLE Job ADD referenceDatabase VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE Job DROP FOREIGN KEY FK_C395A618B7371B00');
        $this->addSql('DROP INDEX IDX_C395A618B7371B00 ON Job');
        $this->addSql('ALTER TABLE Job DROP metagenome_db_id');
    }
}
