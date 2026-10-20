<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227170419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job CHANGE resultFileStats resultFileStats LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE PFAM_clans CHANGE pfam_id pfam_id VARCHAR(255) NOT NULL, ADD PRIMARY KEY (pfam_id)');
        $this->addSql('ALTER TABLE family_info CHANGE family family VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Job CHANGE resultFileStats resultFileStats VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX `primary` ON PFAM_clans');
        $this->addSql('ALTER TABLE PFAM_clans CHANGE pfam_id pfam_id VARCHAR(24) DEFAULT NULL');
        $this->addSql('ALTER TABLE family_info CHANGE family family VARCHAR(10) NOT NULL');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
    }
}
