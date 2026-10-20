<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415163803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE Job SET taxSearch = NULL WHERE taxSearch = ''");

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE Taxon');
        $this->addSql('ALTER TABLE Job CHANGE taxSearch taxSearch JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Taxon (Taxon_id INT DEFAULT NULL, domain VARCHAR(25) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, kingdom VARCHAR(25) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, phylum VARCHAR(30) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, class VARCHAR(25) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, tax_order VARCHAR(30) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, family VARCHAR(25) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, genus VARCHAR(40) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, species VARCHAR(50) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_general_ci`, INDEX genus_index (genus), INDEX class_index (class), INDEX domain_index (domain), INDEX species_index (species), INDEX tax_order_index (tax_order), INDEX kingdom_index (kingdom), INDEX family_index (family), INDEX phylum_index (phylum), INDEX tax_id_index (Taxon_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Job CHANGE taxSearch taxSearch VARCHAR(255) DEFAULT NULL');
    }
}
