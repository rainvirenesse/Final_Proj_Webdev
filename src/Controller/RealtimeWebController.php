<?php



declare(strict_types=1);



namespace App\Controller;



use App\Entity\User;

use App\Service\Admin\StaffNotificationPollService;
use App\Service\Api\StaffOrderApiService;

use App\Service\Mercure\MercureTopicFactory;

use App\Service\Realtime\RealtimeSubscribeTokenService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;



/**

 * Session-authenticated realtime for the admin/staff web dashboard (Mercure + WebSocket).

 */

#[Route('/realtime')]

#[IsGranted('ROLE_STAFF')]

final class RealtimeWebController extends AbstractController

{

    #[Route('/config', name: 'realtime_web_config', methods: ['GET'])]

    public function config(

        Request $request,

        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]

        string $publicWsUrl,

    ): JsonResponse {

        return new JsonResponse([

            'websocketUrl' => $this->resolvePublicUrl($publicWsUrl, $request),

            'enabled' => $publicWsUrl !== '',

        ]);

    }



    #[Route('/token', name: 'realtime_web_token', methods: ['GET'])]

    public function token(

        RealtimeSubscribeTokenService $tokens,

        #[Autowire('%env(REALTIME_WS_PUBLIC_URL)%')]

        string $publicWsUrl,

    ): JsonResponse {

        if ($publicWsUrl === '') {

            return new JsonResponse(['error' => 'Realtime is not enabled.'], 503);

        }



        $user = $this->getUser();

        if (!$user instanceof User) {

            return new JsonResponse(['error' => 'Unauthorized'], 401);

        }



        try {

            $subscribeToken = $tokens->issueForUser($user);

        } catch (\RuntimeException $e) {

            return new JsonResponse(['error' => 'Realtime is not configured.'], 503);

        }



        return new JsonResponse([

            'subscribeToken' => $subscribeToken,

            'expiresIn' => 3600,

        ]);

    }



    #[Route('/mercure/config', name: 'realtime_web_mercure_config', methods: ['GET'])]

    public function mercureConfig(

        Request $request,

        MercureTopicFactory $topics,

        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]

        string $mercurePublicUrl,

    ): JsonResponse {

        $hub = $this->resolvePublicUrl($mercurePublicUrl, $request, 'http');

        return new JsonResponse([

            'enabled' => $mercurePublicUrl !== '' && !str_contains($hub, 'example.com'),

            'hub' => $hub,

            'topic' => $topics->orders(),

        ]);

    }



    #[Route('/mercure/token', name: 'realtime_web_mercure_token', methods: ['GET'])]

    public function mercureToken(

        Request $request,

        TokenFactoryInterface $defaultTokenFactory,

        MercureTopicFactory $topics,

        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]

        string $mercurePublicUrl,

    ): JsonResponse {

        if ($mercurePublicUrl === '') {

            return new JsonResponse(['error' => 'Mercure is not enabled.'], 503);

        }



        return new JsonResponse([

            'hub' => $this->resolvePublicUrl($mercurePublicUrl, $request, 'http'),

            'topic' => $topics->orders(),

            'token' => $defaultTokenFactory->create(

                subscribe: [$topics->orders()],

            ),

        ]);

    }



    #[Route('/poll', name: 'realtime_web_poll', methods: ['GET'])]
    public function poll(Request $request, StaffNotificationPollService $pollService): JsonResponse
    {
        $sinceParam = $request->query->get('since');
        try {
            $since = $sinceParam
                ? new \DateTimeImmutable((string) $sinceParam)
                : new \DateTimeImmutable('-10 seconds');
        } catch (\Exception) {
            $since = new \DateTimeImmutable('-10 seconds');
        }

        return new JsonResponse($pollService->poll($since));
    }

    #[Route('/orders/{id}/approve', name: 'realtime_web_order_approve', methods: ['POST'], requirements: ['id' => '\d+'])]

    public function approveOrder(int $id, StaffOrderApiService $orders): JsonResponse

    {

        try {

            $order = $orders->approve($id);



            return new JsonResponse([

                'success' => true,

                'order' => $orders->serializeOrder($order),

            ]);

        } catch (\InvalidArgumentException $e) {

            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);

        }

    }



    private function resolvePublicUrl(string $url, Request $request, ?string $defaultScheme = null): string

    {

        if ($url === '') {

            return '';

        }



        $parts = parse_url($url);

        if (!\is_array($parts) || !isset($parts['host'])) {

            return $url;

        }



        $host = strtolower((string) $parts['host']);

        if ($host !== 'localhost' && $host !== '127.0.0.1') {

            return $url;

        }



        $requestHost = $request->getHost();

        if ($requestHost === '' || $requestHost === 'localhost' || $requestHost === '127.0.0.1') {

            return $url;

        }



        $scheme = $parts['scheme'] ?? $defaultScheme ?? 'ws';

        if ($defaultScheme === 'http' && $scheme === 'ws') {

            $scheme = 'http';

        }

        if ($defaultScheme === 'http' && $scheme === 'wss') {

            $scheme = 'https';

        }



        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $path = $parts['path'] ?? '';

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';



        return sprintf('%s://%s%s%s%s', $scheme, $requestHost, $port, $path, $query);

    }

}


