<?php

namespace App\Controller\Api\Customer;

use App\Api\ApiResponse;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/customer/profile')]
final class ProfileController extends AbstractCustomerApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_customer_profile_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $this->requireCustomerApi();

        return ApiResponse::success($this->serializeProfile($this->requireUser()));
    }

    #[Route('', name: 'api_customer_profile_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $this->requireCustomerApi();
        $user = $this->requireUser();
        $data = $this->decodeJson($request);

        if (isset($data['username'])) {
            $username = trim((string) $data['username']);
            $violations = $this->validator->validate($username, [
                new Assert\NotBlank(),
                new Assert\Length(min: 3, max: 50),
                new Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/'),
            ]);
            if (\count($violations) > 0) {
                return $this->validationError($violations);
            }
            $user->setUsername($username);
        }

        try {
            $this->em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return ApiResponse::error('Username is already taken.', 409);
        }

        return ApiResponse::success($this->serializeProfile($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(User $user): array
    {
        $roles = $user->getRoles();
        $primaryRole = \in_array('ROLE_ADMIN', $roles, true)
            ? 'Admin'
            : (\in_array('ROLE_STAFF', $roles, true) ? 'Staff' : 'Customer');

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'role' => $primaryRole,
            'roles' => $roles,
            'status' => $user->getStatus(),
            'verified' => $user->isVerified(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
