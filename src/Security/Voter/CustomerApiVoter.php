<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Customer-only mobile API routes (cart, orders, payments).
 */
final class CustomerApiVoter extends Voter
{
    public const CUSTOMER_API = 'CUSTOMER_API';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CUSTOMER_API;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
            return false;
        }

        return \in_array('ROLE_CUSTOMER', $roles, true) || \in_array('ROLE_USER', $roles, true);
    }
}
