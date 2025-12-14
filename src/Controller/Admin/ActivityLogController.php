<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activity-logs')]
final class ActivityLogController extends AbstractController
{
    #[Route(name: 'admin_activity_log_index', methods: ['GET'])]
    public function index(
        ActivityLogRepository $activityLogRepository,
        UserRepository $userRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Get filter parameters
        $userId = $request->query->get('user');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        
        // Build query with left join to handle potential null users
        $qb = $activityLogRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC');
        
        if ($userId && is_numeric($userId)) {
            $qb->andWhere('a.user = :userId')
               ->setParameter('userId', (int)$userId);
        }
        
        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }
        
        if ($dateFrom) {
            try {
                $qb->andWhere('a.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }
        
        if ($dateTo) {
            try {
                $qb->andWhere('a.createdAt <= :dateTo')
                   ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }
        
        $logs = $qb->setMaxResults(500)->getQuery()->getResult();
        $users = $userRepository->findAll();

        return $this->render('admin/activity_log/index.html.twig', [
            'logs' => $logs,
            'users' => $users,
            'currentFilters' => [
                'user' => $userId,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}

