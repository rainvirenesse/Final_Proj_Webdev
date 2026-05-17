<?php

namespace App\Controller\Staff;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/staff/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route(name: 'staff_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Staff can view all users
        $users = $userRepository->findAll();

        return $this->render('staff/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'staff_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Validate required fields
                    if (!$user->getEmail()) {
                        throw new \InvalidArgumentException('Email is required.');
                    }
                    
                    if (!$user->getUsername()) {
                        throw new \InvalidArgumentException('Username is required.');
                    }
                    
                    // Validate email format
                    if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException('Please enter a valid email address.');
                    }
                    
                    // Validate username format
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $user->getUsername())) {
                        throw new \InvalidArgumentException('Username can only contain letters, numbers, and underscores.');
                    }
                    
                    // Hash password (required for new users)
                    $plainPassword = $form->get('password')->getData();
                    if (!$plainPassword) {
                        throw new \InvalidArgumentException('Password is required for new users.');
                    }
                    
                    if (strlen($plainPassword) < 6) {
                        throw new \InvalidArgumentException('Password must be at least 6 characters long.');
                    }
                    
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                    $user->setIsVerified(true);
                    
                    // Ensure roles are set
                    if (empty($user->getRoles())) {
                        $user->setRoles(['ROLE_USER']);
                    }
                    
                    // Ensure status is set
                    if (!$user->getStatus()) {
                        $user->setStatus(User::STATUS_ACTIVE);
                    }
                    
                    // Handle image upload
                    $imageFile = $form->get('image')->getData();
                    if ($imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                        try {
                            $imageFile->move(
                                $this->getParameter('user_images_directory'),
                                $newFilename
                            );
                            $user->setImage($newFilename);
                        } catch (FileException $e) {
                            $this->addFlash('warning', 'Image could not be uploaded, but user will be created.');
                        }
                    }

                    $entityManager->persist($user);
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'User created successfully!');
                    return $this->redirectToRoute('staff_user_index', [], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException $e) {
                    if (str_contains($e->getMessage(), 'email') || str_contains($e->getMessage(), 'user.email')) {
                        $this->addFlash('danger', 'A user with this email already exists. Please use a different email.');
                    } elseif (str_contains($e->getMessage(), 'username') || str_contains($e->getMessage(), 'user.username')) {
                        $this->addFlash('danger', 'A user with this username already exists. Please use a different username.');
                    } else {
                        $this->addFlash('danger', 'A user with this identifier already exists.');
                    }
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'An error occurred while creating the user: ' . $e->getMessage());
                }
            } else {
                // Form validation failed
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('danger', ' error: ' . implode(', ', $errors));
                }
            }
        }

        return $this->render('staff/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'staff_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Staff can view all users
        return $this->render('staff/user/show.html.twig', [
            'user' => $user,
        ]);
    }
}

