<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430045136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stock_record table for inventory movement history.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('stock_record')) {
            return;
        }

        if (!$schema->hasTable('product') || !$schema->hasTable('user')) {
            return;
        }

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_record (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, created_by_id INT DEFAULT NULL, quantity_delta INT NOT NULL, note VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_51124D074584665A (product_id), INDEX IDX_51124D07B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_record ADD CONSTRAINT FK_51124D074584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_record ADD CONSTRAINT FK_51124D07B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_record DROP FOREIGN KEY FK_51124D074584665A');
        $this->addSql('ALTER TABLE stock_record DROP FOREIGN KEY FK_51124D07B03A8386');
        $this->addSql('DROP TABLE stock_record');
    }
}
