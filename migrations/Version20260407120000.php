<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.last_activity_at for dashboard login presence (Active / Inactive).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user') || $schema->getTable('user')->hasColumn('last_activity_at')) {
            return;
        }

        $this->addSql('ALTER TABLE `user` ADD last_activity_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP last_activity_at');
    }
}
