<?php

namespace App\Controller\Admin;

use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        CustomerOrderRepository $orderRepository,
        ServiceRepository $serviceRepository,
        ProductRepository $productRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $activityLogRepository = $this->entityManager->getRepository(\App\Entity\ActivityLog::class);
        $recentOrders = $orderRepository->findBy([], ['orderedAt' => 'DESC'], 8);
        $recentActivities = $activityLogRepository->findRecent(8);

        return $this->render('admin/home.html.twig', $this->buildStatsViewData(
            $userRepository,
            $orderRepository,
            $serviceRepository,
            $productRepository,
            $recentOrders,
            $recentActivities,
        ));
    }

    #[Route('/admin/dashboard/live-stats', name: 'admin_dashboard_live_stats', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function liveStats(
        UserRepository $userRepository,
        CustomerOrderRepository $orderRepository,
        ServiceRepository $serviceRepository,
        ProductRepository $productRepository,
    ): JsonResponse {
        $data = $this->buildStatsViewData(
            $userRepository,
            $orderRepository,
            $serviceRepository,
            $productRepository,
            [],
            [],
        );

        return new JsonResponse([
            'totalUsers' => $data['totalUsers'],
            'totalStaff' => $data['totalStaff'],
            'totalOrders' => $data['totalOrders'],
            'activeOrders' => $data['activeOrders'],
            'completedOrders' => $data['completedOrders'],
            'totalRevenueFormatted' => '₱'.number_format((float) $data['totalRevenue'], 2, '.', ','),
            'totalProducts' => $data['totalProducts'],
            'activeProducts' => $data['activeProducts'],
            'totalProductStock' => $data['totalProductStock'],
            'totalServices' => $data['totalServices'],
        ]);
    }

    /**
     * @param list<\App\Entity\CustomerOrder> $recentOrders
     * @param list<\App\Entity\ActivityLog>   $recentActivities
     *
     * @return array<string, mixed>
     */
    private function buildStatsViewData(
        UserRepository $userRepository,
        CustomerOrderRepository $orderRepository,
        ServiceRepository $serviceRepository,
        ProductRepository $productRepository,
        array $recentOrders,
        array $recentActivities,
    ): array {
        $totalUsers = \count($userRepository->findAll());
        $allOrders = $orderRepository->findAll();
        $totalOrders = \count($allOrders);

        $activeOrders = 0;
        $completedOrders = 0;
        foreach ($allOrders as $order) {
            if (\in_array($order->getStatus(), ['IN_PROGRESS', 'PENDING'], true)) {
                ++$activeOrders;
            }
            if ($order->getStatus() === 'COMPLETED') {
                ++$completedOrders;
            }
        }

        $totalRevenue = 0.0;
        foreach ($orderRepository->findBy(['status' => 'COMPLETED']) as $order) {
            $totalRevenue += (float) $order->getTotalPrice();
        }

        $totalStaff = 0;
        foreach ($userRepository->findAll() as $user) {
            if (\in_array('ROLE_STAFF', $user->getRoles(), true) || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                ++$totalStaff;
            }
        }

        return [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalOrders' => $totalOrders,
            'activeOrders' => $activeOrders,
            'totalRevenue' => $totalRevenue,
            'completedOrders' => $completedOrders,
            'totalProducts' => $productRepository->countProducts(),
            'activeProducts' => $productRepository->countActiveProducts(),
            'totalProductStock' => $productRepository->sumStock(),
            'totalServices' => \count($serviceRepository->findAll()),
            'recentOrders' => $recentOrders,
            'recentActivities' => $recentActivities,
        ];
    }
}
