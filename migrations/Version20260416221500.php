<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416221500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds per-user timezone preference column.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if (!$table->hasColumn('timezone')) {
            $table->addColumn('timezone', 'string', [
                'length' => 64,
                'notnull' => false,
            ]);
        }

    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if ($table->hasColumn('timezone')) {
            $table->dropColumn('timezone');
        }
    }
}
