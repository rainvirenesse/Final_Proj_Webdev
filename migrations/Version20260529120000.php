<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff_approved_at to customer_order for dashboard approve flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order ADD staff_approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP staff_approved_at');
    }
}
