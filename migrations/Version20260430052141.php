<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430052141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last-edited audit (updated_at, updated_by) on stock_record.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_record ADD updated_by_id INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stock_record ADD CONSTRAINT FK_51124D07896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_51124D07896DBBDE ON stock_record (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_record DROP FOREIGN KEY FK_51124D07896DBBDE');
        $this->addSql('DROP INDEX IDX_51124D07896DBBDE ON stock_record');
        $this->addSql('ALTER TABLE stock_record DROP updated_by_id, DROP updated_at');
    }
}
