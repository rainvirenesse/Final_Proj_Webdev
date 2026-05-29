<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\StockRecord;
use App\Entity\User;
use App\Service\ShopCatalog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get admin user for createdBy
        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'raincredo91@gmail.com']);
        if (!$admin) {
            return; // UserFixtures must be loaded first
        }

        $imageByName = (new ShopCatalog())->getProductImageMap();

        $shoes = [
            [
                'name' => 'Two-Tone Pointed Stiletto Heels',
                'description' => 'Elegant pointed heels with satin finish.',
                'price' => 2799.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Classic Deep Wine Pumps',
                'description' => 'Timeless block-heeled silhouette.',
                'price' => 3199.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Low-Heel Mary Jane Flats',
                'description' => 'Comfortable low-heel design.',
                'price' => 1899.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Slingback Bow Block Heels',
                'description' => 'Refined slingback with bow detail.',
                'price' => 2499.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Braided Strap Slip-On Sandals',
                'description' => 'Woven leather upper, cushioned sole.',
                'price' => 1699.00,
                'category' => 'Sandals',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Knee-High Square Heel Boots',
                'description' => 'Luxe suede finish, block heel.',
                'price' => 5299.00,
                'category' => 'Boots',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Platform Ankle-Strap High Heels',
                'description' => 'Thick platform with ankle strap.',
                'price' => 3599.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Chain Accent Loafers',
                'description' => 'Classic loafer with gold chain detail.',
                'price' => 2899.00,
                'category' => 'Outdoor',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Feather Trim Mule Heels',
                'description' => 'Fluffy feather trim with sleek block heel.',
                'price' => 3299.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Wrap-Around Lace Heels',
                'description' => 'Delicate lace-up wrap with stiletto heel.',
                'price' => 2999.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Pointed Cap-Toe Flats',
                'description' => 'Two-tone cap toe with slim pointed silhouette.',
                'price' => 2199.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Buckle Strap Platform Boots',
                'description' => 'Bold platform sole with multi-buckle straps.',
                'price' => 5499.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Square-Toe Satin Mules',
                'description' => 'Soft satin finish with cushioned insole.',
                'price' => 2199.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Minimalist Leather Slides',
                'description' => 'Clean lines and all-day comfort footbed.',
                'price' => 1799.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Lace-Up Chunky Sneakers',
                'description' => 'Street-ready profile with lightweight sole.',
                'price' => 2999.00,
                'category' => 'Outdoor',
                'status' => Product::STATUS_ACTIVE,
            ],
             [
                'name' => 'Classic Penny Loafers',
                'description' => 'Polished leather upper and flexible base.',
                'price' => 2699.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
             [
                'name' => 'Open-Toe Block Sandals',
                'description' => 'Balanced heel height for everyday wear.',
                'price' => 2099.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
             [
                'name' => 'Knit Slip-On Trainers',
                'description' => 'Breathable upper with responsive cushioning.',
                'price' => 2399.00,
                'category' => 'Outdoor',
                'status' => Product::STATUS_ACTIVE,
            ],
             [
                'name' => 'Everyday Ballet Flats',
                'description' => 'Soft lining and flexible comfort sole.',
                'price' => 2599.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
             [
                'name' => 'Signature Ankle Boots',
                'description' => 'Structured upper with side-zip entry.',
                'price' => 4499.00,
                'category' => 'Outdoor',
                'status' => Product::STATUS_ACTIVE,
            ],
        ];

        foreach ($shoes as $shoeData) {
            $product = new Product();
            $product->setName($shoeData['name']);
            $product->setDescription($shoeData['description']);
            $product->setPrice((string) $shoeData['price']);
            $product->setCategory($shoeData['category']);
            $product->setStatus($shoeData['status']);
            // Seed inventory so dashboard/product tables can display stock.
            // If you want fixed values, replace this with $shoeData['stock'].
            $stockQty = (int) ($shoeData['stock'] ?? rand(5, 30));
            $product->setStock($stockQty);
            $product->setCreatedBy($admin);
            // createdAt and updatedAt are set automatically via lifecycle callbacks

            $image = $shoeData['image'] ?? $imageByName[$shoeData['name']] ?? null;
            if ($image !== null) {
                $product->setImage($image);
            }

            $manager->persist($product);

            // Ledger row matching on-hand stock (does not re-apply delta; product.stock already set).
            if ($stockQty !== 0) {
                $opening = new StockRecord();
                $opening->setProduct($product);
                $opening->setQuantityDelta($stockQty);
                $opening->setCreatedBy($admin);
                $manager->persist($opening);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}