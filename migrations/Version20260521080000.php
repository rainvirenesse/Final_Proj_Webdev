<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment table for order transactions.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('payment')) {
            return;
        }

        if (!$schema->hasTable('customer_order')) {
            return;
        }

        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, transaction_reference VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL, method VARCHAR(50) DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6D28895D8D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28895D8D9F6D38 FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28895D8D9F6D38');
        $this->addSql('DROP TABLE payment');
    }
}
