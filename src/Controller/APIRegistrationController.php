<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class APIRegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, UserRegistrationService $registrationService, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $username = isset($data['username']) ? trim((string) $data['username']) : '';
        $plainPassword = isset($data['password']) ? (string) $data['password'] : '';

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword('');

        $errors = $validator->validate($user);
        $errors->addAll($validator->validate($plainPassword, [
            new Assert\NotBlank(message: 'Password cannot be blank.'),
            new Assert\Length(min: 6, minMessage: 'Password must be at least {{ limit }} characters long.'),
        ]));

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $e) {
                $path = $e->getPropertyPath();
                $key = $path !== '' ? $path : 'password';
                $messages[$key] = $e->getMessage();
            }

            return new JsonResponse(['error' => 'Validation failed.', 'violations' => $messages], 422);
        }

        try {
            $registrationService->register($user, $plainPassword);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        return new JsonResponse([
            'message' => 'Registration successful. Check your email to verify your account.',
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'verified' => false,
        ], 201);
    }
}
