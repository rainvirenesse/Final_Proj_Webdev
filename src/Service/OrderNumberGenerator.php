<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Repository\CustomerOrderRepository;

final class OrderNumberGenerator
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
    ) {
    }

    public function generate(): string
    {
        $maxAttempts = 100;
        $datePrefix = (new \DateTime())->format('Ymd');

        for ($attempt = 0; $attempt < $maxAttempts; ++$attempt) {
            $randomSuffix = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $orderNumber = 'ORD-'.$datePrefix.'-'.$randomSuffix;

            if ($this->orderRepository->findOneBy(['orderNumber' => $orderNumber]) === null) {
                return $orderNumber;
            }
        }

        return 'ORD-'.$datePrefix.'-'.time();
    }
}
