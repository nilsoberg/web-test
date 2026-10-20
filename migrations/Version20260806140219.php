<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260806140219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job ADD numCdhitClusters INT DEFAULT NULL, ADD numMarkers INT DEFAULT NULL, ADD numUniqueSeq INT DEFAULT NULL, ADD numConsensusSeqWithHits INT DEFAULT NULL, ADD axaSearchType VARCHAR(255) DEFAULT NULL, ADD axaSearchSens VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job DROP numCdhitClusters, DROP numMarkers, DROP numUniqueSeq, DROP numConsensusSeqWithHits, DROP axaSearchType, DROP axaSearchSens');
    }
}
