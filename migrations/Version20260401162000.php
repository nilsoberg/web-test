<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds state tracking for async job-status change email notifications.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('JobStatusNotificationState')) {
            return;
        }

        $table = $schema->createTable('JobStatusNotificationState');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('job_id', 'integer');
        $table->addColumn('lastNotifiedStatus', 'string', ['length' => 255]);
        $table->addColumn('lastNotifiedAt', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['job_id'], 'UNIQ_6466A0D1BE04EA9');
        $table->addForeignKeyConstraint(
            'Job',
            ['job_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_6466A0D1BE04EA9'
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('JobStatusNotificationState')) {
            $schema->dropTable('JobStatusNotificationState');
        }
    }
}
