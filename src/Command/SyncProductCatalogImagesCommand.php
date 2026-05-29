<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\ShopCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:products:sync-catalog-images',
    description: 'Set product.image from the shop catalog filenames so mobile API returns imageUrl'
)]
final class SyncProductCatalogImagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopCatalog $shopCatalog,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $imageByName = $this->shopCatalog->getProductImageMap();

        $products = $this->entityManager->getRepository(Product::class)->findAll();
        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $name = $product->getName();
            if ($name === null || !isset($imageByName[$name])) {
                ++$skipped;
                continue;
            }

            $filename = $imageByName[$name];
            if ($product->getImage() === $filename) {
                continue;
            }

            $product->setImage($filename);
            ++$updated;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Updated %d product image(s). Skipped %d (no catalog match).',
            $updated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
