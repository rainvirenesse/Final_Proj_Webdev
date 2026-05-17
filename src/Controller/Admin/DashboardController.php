<?php

namespace App\Controller\Admin;

use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        CustomerOrderRepository $orderRepository,
        ServiceRepository $serviceRepository,
        ProductRepository $productRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        // Get statistics
        $totalUsers = count($userRepository->findAll());
        $allOrders = $orderRepository->findAll();
        $totalOrders = count($allOrders);
        
        $activeOrders = 0;
        $completedOrders = 0;
        foreach ($allOrders as $order) {
            if (in_array($order->getStatus(), ['IN_PROGRESS', 'PENDING'])) {
                $activeOrders++;
            }
            if ($order->getStatus() === 'COMPLETED') {
                $completedOrders++;
            }
        }
        
        // Calculate revenue from completed orders
        $completedOrdersList = $orderRepository->findBy(['status' => 'COMPLETED']);
        $totalRevenue = 0;
        foreach ($completedOrdersList as $order) {
            $totalRevenue += (float) $order->getTotalPrice();
        }
        
        $totalServices = count($serviceRepository->findAll());
        $totalProducts = $productRepository->countProducts();
        $activeProducts = $productRepository->countActiveProducts();
        $totalProductStock = $productRepository->sumStock();
        
        // Count staff users
        $allUsers = $userRepository->findAll();
        $totalStaff = 0;
        foreach ($allUsers as $user) {
            if (in_array('ROLE_STAFF', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles())) {
                $totalStaff++;
            }
        }
        
        // Get recent orders (latest 8)
        $recentOrders = $orderRepository->findBy(
            [],
            ['orderedAt' => 'DESC'],
            8
        );
        
        // Get recent activities (latest 8)
        $activityLogRepository = $this->entityManager->getRepository(\App\Entity\ActivityLog::class);
        $recentActivities = $activityLogRepository->findRecent(8);

        return $this->render('admin/home.html.twig', [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalOrders' => $totalOrders,
            'activeOrders' => $activeOrders,
            'totalRevenue' => $totalRevenue,
            'completedOrders' => $completedOrders,
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'totalProductStock' => $totalProductStock,
            'totalServices' => $totalServices,
            'recentOrders' => $recentOrders,
            'recentActivities' => $recentActivities,
        ]);
    }
}
