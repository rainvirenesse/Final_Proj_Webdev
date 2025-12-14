<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251214000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add createdBy field to service and customer_order tables';
    }

    public function up(Schema $schema): void
    {
        // Add created_by to service table
        $this->addSql('ALTER TABLE service ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_E19D9AD2B03A8386 ON service (created_by_id)');
        
        // Add created_by to customer_order table
        $this->addSql('ALTER TABLE customer_order ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B20604EB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_3B20604EB03A8386 ON customer_order (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove created_by from customer_order table
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B20604EB03A8386');
        $this->addSql('DROP INDEX IDX_3B20604EB03A8386 ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP created_by_id');
        
        // Remove created_by from service table
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2B03A8386');
        $this->addSql('DROP INDEX IDX_E19D9AD2B03A8386 ON service');
        $this->addSql('ALTER TABLE service DROP created_by_id');
    }
}

