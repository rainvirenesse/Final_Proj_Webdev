<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\StockRecord;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stock:import-opening-from-products',
    description: 'Create opening stock ledger rows from each product\'s on-hand quantity (products with no records yet; does not change Product.stock)'
)]
final class ImportOpeningStockFromProductsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $defaultUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'rain@gmail.com']);

        $products = $this->entityManager->getRepository(Product::class)->findAll();
        $stockRepo = $this->entityManager->getRepository(StockRecord::class);
        $created = 0;

        foreach ($products as $product) {
            if ($stockRepo->count(['product' => $product]) > 0) {
                continue;
            }

            $qty = $product->getStock();
            if ($qty === 0) {
                continue;
            }

            $record = new StockRecord();
            $record->setProduct($product);
            $record->setQuantityDelta($qty);
            if ($defaultUser instanceof User) {
                $record->setCreatedBy($defaultUser);
            }
            $this->entityManager->persist($record);
            ++$created;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Created %d opening stock record(s). Skipped products that already have ledger rows or zero stock.', $created));

        return Command::SUCCESS;
    }
}
