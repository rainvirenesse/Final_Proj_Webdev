<?php

namespace App\Service\Api;

use App\Entity\Product;
use App\Service\ProductImageUrlResolver;

final class ProductSerializer
{
    public function __construct(
        private readonly ProductImageUrlResolver $imageUrlResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Product $product, bool $detailed = false): array
    {
        $stock = $product->getStock();

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'status' => $product->getStatus(),
            'stock' => $stock,
            'inventoryStock' => $stock,
            'inStock' => $stock > 0 && $product->getStatus() === Product::STATUS_ACTIVE,
            'category' => $product->getCategory(),
            'image' => $product->getImage(),
            'imagePath' => $this->imageUrlResolver->resolvePublicPath($product->getImage()),
            'imageUrl' => $this->imageUrlResolver->resolveAbsoluteUrl($product->getImage()),
        ];

        if ($detailed) {
            $data['description'] = $product->getDescription();
            $data['createdAt'] = $product->getCreatedAt()?->format(\DateTimeInterface::ATOM);
            $data['updatedAt'] = $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM);
        }

        return $data;
    }
}
