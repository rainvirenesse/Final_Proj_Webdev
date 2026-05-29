<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill product.image from shop catalog filenames for mobile app';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('product')) {
            return;
        }

        $map = [
            'Two-Tone Pointed Stiletto Heels' => 'stelitto.png',
            'Classic Deep Wine Pumps' => 'pumpp.png',
            'Low-Heel Mary Jane Flats' => 'maryjane.png',
            'Slingback Bow Block Heels' => 'bow.png',
            'Braided Strap Slip-On Sandals' => 'braided.png',
            'Knee-High Square Heel Boots' => 'boots.png',
            'Platform Ankle-Strap High Heels' => 'strap.png',
            'Chain Accent Loafers' => 'loafer.png',
            'Feather Trim Mule Heels' => 'feather.png',
            'Wrap-Around Lace Heels' => 'lace_heels.png',
            'Pointed Cap-Toe Flats' => 'captoe.png',
            'Buckle Strap Platform Boots' => 'platform_boots.png',
            'Square-Toe Satin Mules' => 'mules.png',
            'Minimalist Leather Slides' => 'leather.png',
            'Lace-Up Chunky Sneakers' => 'sneakers.png',
            'Classic Penny Loafers' => 'loafers.png',
            'Open-Toe Block Sandals' => 'opentoe.png',
            'Knit Slip-On Trainers' => 'trainers.png',
            'Everyday Ballet Flats' => 'ballet.png',
            'Signature Ankle Boots' => 'boots_red.png',
        ];

        foreach ($map as $name => $image) {
            $this->addSql(sprintf(
                "UPDATE product SET image = '%s' WHERE name = '%s'",
                str_replace("'", "''", $image),
                str_replace("'", "''", $name),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        // Data backfill — no schema rollback.
    }
}
