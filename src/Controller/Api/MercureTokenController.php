<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Mercure\MercureTopicFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mercure')]
final class MercureTokenController extends AbstractController
{
    #[Route('/token', name: 'api_mercure_token', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffSubscribeToken(
        TokenFactoryInterface $defaultTokenFactory,
        MercureTopicFactory $topics,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]
        string $mercurePublicUrl,
    ): JsonResponse {
        return $this->json([
            'token' => $defaultTokenFactory->create(
                subscribe: [$topics->orders()],
            ),
            'hub' => $mercurePublicUrl,
            'topic' => $topics->orders(),
        ]);
    }
}
