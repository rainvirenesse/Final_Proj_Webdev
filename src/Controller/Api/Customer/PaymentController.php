<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Service\Api\CustomerPaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/orders/{orderId}/payment', requirements: ['orderId' => '\d+'])]
final class PaymentController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerPaymentService $paymentService,
    ) {
    }

    #[Route('', name: 'api_customer_payment_show', methods: ['GET'])]
    public function show(int $orderId): JsonResponse
    {
        $this->requireCustomerApi();

        try {
            return ApiResponse::success($this->paymentService->getPaymentStatus($this->requireUser(), $orderId));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    #[Route('', name: 'api_customer_payment_process', methods: ['POST'])]
    public function process(int $orderId, Request $request): JsonResponse
    {
        $this->requireCustomerApi();

        try {
            $result = $this->paymentService->processPayment(
                $this->requireUser(),
                $orderId,
                $this->decodeJson($request)
            );

            return ApiResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
