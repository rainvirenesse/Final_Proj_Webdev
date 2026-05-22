<?php

namespace App\Controller\Api;

use App\Api\ApiResponse;
use App\Repository\ProductRepository;
use App\Service\Api\ProductSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public product catalog (standard JSON). Takes precedence over Api Platform Hydra listing.
 */
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductSerializer $productSerializer,
    ) {
    }

    #[Route('/api/products', name: 'api_products_list', methods: ['GET'], priority: 100)]
    public function list(): JsonResponse
    {
        $products = $this->productRepository->findCatalogProducts();
        $data = array_map(
            fn ($p) => $this->productSerializer->toArray($p),
            $products
        );

        return ApiResponse::success($data, 200, ['count' => \count($data)]);
    }

    #[Route('/api/products/{id}', name: 'api_products_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: 100)]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (
            !$product
            || $product->getStatus() === \App\Entity\Product::STATUS_INACTIVE
        ) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success($this->productSerializer->toArray($product, true));
    }
}
