<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserRegistrationService $userRegistrationService, LoggerInterface $logger): Response
    {
        $logger->info('Registration process started.');
        
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $logger->info('Registration form submitted.');
            
            if ($form->isValid()) {
                $logger->info('Registration form is valid. Attempting to register user: ' . $user->getEmail());
                
                /** @var string $plainPassword */
                $plainPassword = $form->get('password')->getData();

                try {
                    $userRegistrationService->register($user, $plainPassword);
                    $logger->info('Registration successful for user: ' . $user->getEmail());
                    $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
                    return $this->redirectToRoute('login');
                } catch (\InvalidArgumentException $e) {
                    // Expected validation errors (e.g. duplicate username/email)
                    $logger->warning('Registration validation error: ' . $e->getMessage());
                    $this->addFlash('error', $e->getMessage());
                } catch (\Throwable $e) {
                    // Unexpected errors (Database missing, Mailer failed, etc.)
                    $logger->error('Unexpected registration error: ' . $e->getMessage(), ['exception' => $e]);
                    
                    $errorMsg = $e->getMessage();
                    if (str_contains($errorMsg, 'Base table or view not found')) {
                        $this->addFlash('error', 'Critical Error: The database tables have not been created. Please ensure database migrations run on deployment.');
                    } elseif (str_contains($errorMsg, 'Connection could not be established')) {
                        $this->addFlash('error', 'Critical Error: Could not connect to the email server. Please check MAILER_DSN.');
                    } else {
                        $this->addFlash('error', 'An unexpected error occurred during registration: ' . $errorMsg);
                    }
                }
            } else {
                $logger->warning('Registration form is INVALID. Form errors: ' . (string) $form->getErrors(true, false));
            }

            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form,
            ]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}