<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250724235901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_global_role DROP FOREIGN KEY FK_FF2573934F68DC3D');
        $this->addSql('ALTER TABLE user_global_role DROP FOREIGN KEY FK_FF257393D60322AC');
        $this->addSql('DROP TABLE user_global_role');
        $this->addSql('ALTER TABLE users ADD global_role_id INT NOT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9BD87E24D FOREIGN KEY (global_role_id) REFERENCES Role (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9BD87E24D ON users (global_role_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_global_role (userentity_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_FF2573934F68DC3D (userentity_id), INDEX IDX_FF257393D60322AC (role_id), PRIMARY KEY(userentity_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_global_role ADD CONSTRAINT FK_FF2573934F68DC3D FOREIGN KEY (userentity_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_global_role ADD CONSTRAINT FK_FF257393D60322AC FOREIGN KEY (role_id) REFERENCES Role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9BD87E24D');
        $this->addSql('DROP INDEX IDX_1483A5E9BD87E24D ON users');
        $this->addSql('ALTER TABLE users DROP global_role_id');
    }
}
