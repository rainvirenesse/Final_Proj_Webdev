<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

     #[Route(path: '/login', name: 'login')]
    // #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            // Redirect based on role
            $user = $this->getUser();
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                return $this->redirectToRoute('admin_dashboard');
            } elseif (in_array('ROLE_STAFF', $roles)) {
                return $this->redirectToRoute('staff_product_index');
            }
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        // Symfony will intercept this route
        throw new \Exception('This should never be reached!');
    }
}
