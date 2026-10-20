<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824181530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DatabaseMetadata ADD metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE databaseType db_type VARCHAR(255) DEFAULT \'est\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE DatabaseMetadata DROP metadata, CHANGE db_type databaseType VARCHAR(255) NOT NULL');
    }
}
