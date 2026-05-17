<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430051722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove note column from stock_record.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_record DROP note');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_record ADD note VARCHAR(500) DEFAULT NULL');
    }
}
