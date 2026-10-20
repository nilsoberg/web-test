<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a mustChangePassword flag for first-login password reset enforcement.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if (!$table->hasColumn('mustChangePassword')) {
            $table->addColumn('mustChangePassword', 'boolean', [
                'default' => false,
                'notnull' => true,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if ($table->hasColumn('mustChangePassword')) {
            $table->dropColumn('mustChangePassword');
        }
    }
}
