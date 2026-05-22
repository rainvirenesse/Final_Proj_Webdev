<?php

namespace App\Controller\Api;

use App\Api\ApiResponse;
use App\Controller\Api\Customer\AbstractCustomerApiController;
use App\Service\Api\CustomerPaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/payments')]
final class MobilePaymentController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly CustomerPaymentService $paymentService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_payments_process', methods: ['POST'])]
    public function process(Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $data = $this->decodeJson($request);

        $violations = $this->validator->validate($data, new Assert\Collection([
            'orderId' => [new Assert\NotNull(), new Assert\Type('integer'), new Assert\Positive()],
            'method' => [new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)])],
            'reference' => [new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 100)])],
        ]));
        if (\count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $result = $this->paymentService->processPayment(
                $this->requireUser(),
                (int) $data['orderId'],
                $data
            );

            return ApiResponse::created($result, 'Payment processed successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
