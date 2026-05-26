<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\StockRecord;
use App\Entity\User;
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

        $shoes = [
            [
                'name' => 'Luxury Leather Oxford',
                'description' => 'Handcrafted premium leather oxford shoes with classic brogue detailing. Perfect for formal occasions and business wear.',
                'price' => 450.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Designer Sneaker Pro',
                'description' => 'High-end athletic sneakers with premium materials and advanced cushioning technology. Ideal for active lifestyle.',
                'price' => 320.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Italian Loafers',
                'description' => 'Elegant Italian-made loafers with genuine leather and sophisticated design. Comfortable slip-on style.',
                'price' => 380.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Executive Derby Shoes',
                'description' => 'Professional derby shoes with polished finish and premium leather construction. Business formal essential.',
                'price' => 420.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Sport Running Elite',
                'description' => 'Professional-grade running shoes with breathable mesh and responsive sole technology. Perfect for athletes.',
                'price' => 280.00,
                'category' => 'Sports',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Classic Monk Strap',
                'description' => 'Timeless monk strap shoes with double buckle closure and premium calfskin leather. Sophisticated style statement.',
                'price' => 495.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Casual Canvas Sneakers',
                'description' => 'Comfortable canvas sneakers with rubber sole and modern design. Perfect for everyday casual wear.',
                'price' => 120.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Premium Boots Collection',
                'description' => 'Durable leather boots with weather-resistant finish and comfortable inner lining. Ideal for outdoor activities.',
                'price' => 550.00,
                'category' => 'Outdoor',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Designer Moccasins',
                'description' => 'Soft suede moccasins with hand-stitched detailing and cushioned insole. Ultimate comfort and style.',
                'price' => 195.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Limited Edition Wingtips',
                'description' => 'Exclusive wingtip brogues with intricate perforated patterns and premium Italian leather. Collector\'s item.',
                'price' => 650.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Athletic Training Shoes',
                'description' => 'Multi-purpose training shoes with stability support and flexible sole. Perfect for gym and cross-training.',
                'price' => 250.00,
                'category' => 'Sports',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Business Casual Slip-Ons',
                'description' => 'Versatile slip-on shoes that bridge formal and casual. Premium materials with modern design.',
                'price' => 275.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Heritage Brogues',
                'description' => 'Classic brogue shoes with traditional craftsmanship and premium full-grain leather. Timeless elegance.',
                'price' => 475.00,
                'category' => 'Formal',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Minimalist Walking Shoes',
                'description' => 'Lightweight walking shoes with minimalist design and maximum comfort. Perfect for long walks.',
                'price' => 180.00,
                'category' => 'Casual',
                'status' => Product::STATUS_ACTIVE,
            ],
            [
                'name' => 'Luxury Evening Shoes',
                'description' => 'Elegant patent leather shoes perfect for formal evening events and special occasions.',
                'price' => 520.00,
                'category' => 'Formal',
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