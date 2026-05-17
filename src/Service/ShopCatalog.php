<?php

namespace App\Service;

/**
 * In-memory catalog for the public shop (landing + product pages).
 * Slug = image filename without extension (e.g. stelitto.png → stelitto).
 */
class ShopCatalog
{
    public const SHIPPING_ZONES = [
        'ncr' => ['label' => 'Metro Manila (NCR)', 'fee' => 80.0],
        'luzon' => ['label' => 'Luzon (outside NCR)', 'fee' => 120.0],
        'visayas' => ['label' => 'Visayas', 'fee' => 150.0],
        'mindanao' => ['label' => 'Mindanao', 'fee' => 150.0],
    ];

    private const SIZES = ['35', '36', '37', '38', '39', '40', '41', '42'];

    /** @return list<array{name: string, subtitle: string, image: string}> */
    public function getCollections(): array
    {
        return [
            ['name' => 'Casual Chic Collection', 'subtitle' => 'Effortless everyday style', 'image' => 'casualchic.png'],
            ['name' => 'Daily Comfort Collection', 'subtitle' => 'All-day wearability', 'image' => 'dailyy.png'],
            ['name' => 'Elegant Heels Collection', 'subtitle' => 'Refined sophistication', 'image' => 'royal.png'],
            ['name' => 'Chic Streetwear Collection', 'subtitle' => 'Bold urban statements', 'image' => 'street.png'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getAllProducts(): array
    {
        return array_values($this->productsBySlug());
    }

    /** @return list<array<string, mixed>> */
    public function getFeaturedProducts(): array
    {
        $by = $this->productsBySlug();
        $order = ['stelitto', 'pumpp', 'maryjane', 'bow', 'braided', 'boots', 'strap', 'loafer'];
        $out = [];
        foreach ($order as $slug) {
            if (isset($by[$slug])) {
                $out[] = $by[$slug];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function getProductBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));

        return $this->productsBySlug()[$slug] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    private function productsBySlug(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $defaults = self::SIZES;
        $rows = [
            ['name' => 'Two-Tone Pointed Stiletto Heels', 'description' => 'Elegant pointed heels with satin finish', 'price' => 2799.0, 'image' => 'stelitto.png', 'tag' => 'New', 'colors' => ['Wine / Burgundy', 'Black', 'Nude'], 'imageStyle' => 'object-position: right center; transform: translate(-50%, -50%) scale(0.85);'],
            ['name' => 'Classic Deep Wine Pumps', 'description' => 'Timeless block-heeled silhouette', 'price' => 3199.0, 'image' => 'pumpp.png', 'tag' => null, 'colors' => ['Deep Wine', 'Black', 'Nude']],
            ['name' => 'Low-Heel Mary Jane Flats', 'description' => 'Comfortable low-heel design', 'price' => 1899.0, 'image' => 'maryjane.png', 'tag' => 'Sale', 'colors' => ['Black', 'Patent Black', 'Cream']],
            ['name' => 'Slingback Bow Block Heels', 'description' => 'Refined slingback with bow detail', 'price' => 2499.0, 'image' => 'bow.png', 'tag' => null, 'colors' => ['Blush', 'Black', 'Ivory']],
            ['name' => 'Braided Strap Slip-On Sandals', 'description' => 'Woven leather upper, cushioned sole', 'price' => 1699.0, 'image' => 'braided.png', 'tag' => null, 'colors' => ['Tan', 'Black', 'White']],
            ['name' => 'Knee-High Square Heel Boots', 'description' => 'Luxe suede finish, block heel', 'price' => 5299.0, 'image' => 'boots.png', 'tag' => 'Best Seller', 'colors' => ['Espresso', 'Black', 'Taupe']],
            ['name' => 'Platform Ankle-Strap High Heels', 'description' => 'Thick platform with ankle strap', 'price' => 3599.0, 'image' => 'strap.png', 'tag' => null, 'colors' => ['Black', 'Nude', 'Silver']],
            ['name' => 'Chain Accent Loafers', 'description' => 'Classic loafer with gold chain detail', 'price' => 2899.0, 'image' => 'loafer.png', 'tag' => null, 'colors' => ['Black', 'Cognac', 'Burgundy']],
            ['name' => 'Feather Trim Mule Heels', 'description' => 'Fluffy feather trim with sleek block heel', 'price' => 3299.0, 'image' => 'feather.png', 'tag' => 'New', 'colors' => ['Black', 'Ivory', 'Blush']],
            ['name' => 'Wrap-Around Lace Heels', 'description' => 'Delicate lace-up wrap with stiletto heel', 'price' => 2999.0, 'image' => 'lace_heels.png', 'tag' => null, 'colors' => ['Black', 'Nude', 'Red']],
            ['name' => 'Pointed Cap-Toe Flats', 'description' => 'Two-tone cap toe with slim pointed silhouette', 'price' => 2199.0, 'image' => 'captoe.png', 'tag' => null, 'colors' => ['Black / Nude', 'Navy / White', 'Black']],
            ['name' => 'Buckle Strap Platform Boots', 'description' => 'Bold platform sole with multi-buckle straps', 'price' => 5499.0, 'image' => 'platform_boots.png', 'tag' => 'Best Seller', 'colors' => ['Black', 'Camel', 'Oxblood']],
            ['name' => 'Square-Toe Satin Mules', 'description' => 'Soft satin finish with cushioned insole', 'price' => 2199.0, 'image' => 'mules.png', 'tag' => 'New', 'colors' => ['Champagne', 'Black', 'Rose']],
            ['name' => 'Minimalist Leather Slides', 'description' => 'Clean lines and all-day comfort footbed', 'price' => 1799.0, 'image' => 'leather.png', 'tag' => null, 'colors' => ['Tan', 'Black', 'White']],
            ['name' => 'Lace-Up Chunky Sneakers', 'description' => 'Street-ready profile with lightweight sole', 'price' => 2999.0, 'image' => 'sneakers.png', 'tag' => null, 'colors' => ['White', 'Black', 'Grey']],
            ['name' => 'Classic Penny Loafers', 'description' => 'Polished leather upper and flexible base', 'price' => 2699.0, 'image' => 'loafers.png', 'tag' => null, 'colors' => ['Black', 'Brown', 'Burgundy']],
            ['name' => 'Open-Toe Block Sandals', 'description' => 'Balanced heel height for everyday wear', 'price' => 2099.0, 'image' => 'opentoe.png', 'tag' => 'Sale', 'colors' => ['Tan', 'Black', 'Gold']],
            ['name' => 'Knit Slip-On Trainers', 'description' => 'Breathable upper with responsive cushioning', 'price' => 2399.0, 'image' => 'trainers.png', 'tag' => null, 'colors' => ['Grey', 'Black', 'Beige']],
            ['name' => 'Everyday Ballet Flats', 'description' => 'Soft lining and flexible comfort sole', 'price' => 1599.0, 'image' => 'ballet.png', 'tag' => null, 'colors' => ['Black', 'Nude', 'Red']],
            ['name' => 'Signature Ankle Boots', 'description' => 'Structured upper with side-zip entry', 'price' => 4499.0, 'image' => 'boots_red.png', 'tag' => 'Best Seller', 'colors' => ['Deep Red', 'Black', 'Taupe']],
        ];

        $cache = [];
        foreach ($rows as $row) {
            $basename = pathinfo($row['image'], PATHINFO_FILENAME);
            $slug = strtolower((string) $basename);
            $cache[$slug] = array_merge($row, [
                'slug' => $slug,
                'sizes' => $defaults,
            ]);
        }

        return $cache;
    }
}
