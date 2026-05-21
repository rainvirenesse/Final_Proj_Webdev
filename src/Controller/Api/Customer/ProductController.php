<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Repository\ProductRepository;
use App\Service\Api\ProductSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/products')]
final class ProductController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductSerializer $productSerializer,
    ) {
    }

    #[Route('', name: 'api_customer_products_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $products = $this->productRepository->findActiveProducts();
        $data = array_map(
            fn ($p) => $this->productSerializer->toArray($p),
            $products
        );

        return ApiResponse::success($data, 200, ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'api_customer_products_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product || $product->getStatus() !== \App\Entity\Product::STATUS_ACTIVE) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success($this->productSerializer->toArray($product, true));
    }
}
