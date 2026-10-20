<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707174219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE DatabaseMetadata (id INT AUTO_INCREMENT NOT NULL, organization_id INT DEFAULT NULL, publicName VARCHAR(255) NOT NULL, dbName VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, isActive TINYINT(1) DEFAULT 0 NOT NULL, isPublic TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_406E989232C8A3DE (organization_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE DatabaseMetadata ADD CONSTRAINT FK_406E989232C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id)');
        $this->addSql('ALTER TABLE Job ADD db_metadata_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Job ADD CONSTRAINT FK_C395A618D3938B28 FOREIGN KEY (db_metadata_id) REFERENCES DatabaseMetadata (id)');
        $this->addSql('CREATE INDEX IDX_C395A618D3938B28 ON Job (db_metadata_id)');
        $this->addSql('ALTER TABLE organizations ADD default_database_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE organizations ADD CONSTRAINT FK_427C1C7F24591A6D FOREIGN KEY (default_database_id) REFERENCES DatabaseMetadata (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_427C1C7F24591A6D ON organizations (default_database_id)');
        $this->addSql('CREATE TABLE AppSetting (settingKey VARCHAR(255) NOT NULL, settingValue LONGTEXT DEFAULT NULL, PRIMARY KEY(settingKey)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job DROP FOREIGN KEY FK_C395A618D3938B28');
        $this->addSql('ALTER TABLE DatabaseMetadata DROP FOREIGN KEY FK_406E989232C8A3DE');
        $this->addSql('ALTER TABLE Job DROP db_metadata_id');
        $this->addSql('ALTER TABLE organizations DROP FOREIGN KEY FK_427C1C7F24591A6D');
        $this->addSql('DROP INDEX IDX_427C1C7F24591A6D ON organizations');
        $this->addSql('ALTER TABLE organizations DROP default_database_id');
        $this->addSql('DROP TABLE AppSetting');
        $this->addSql('DROP TABLE DatabaseMetadata');
        $this->addSql('DROP INDEX IDX_C395A618D3938B28 ON Job');
    }
}
