<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('rain@gmail.com');
        $admin->setUsername('rain');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'rain123');
        $admin->setPassword($hashedPassword);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($admin);

        // Create Staff User 1
        $staff1 = new User();
        $staff1->setEmail('staff@gmail.com');
        $staff1->setUsername('bea');
        $staff1->setRoles(['ROLE_STAFF']);
        $staff1->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($staff1, 'rain123');
        $staff1->setPassword($hashedPassword);
        $staff1->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff1);

        // Create Staff User 2
        $staff2 = new User();
        $staff2->setEmail('staff2@example.com');
        $staff2->setUsername('darl');
        $staff2->setRoles(['ROLE_STAFF']);
        $staff2->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($staff2, 'rain123');
        $staff2->setPassword($hashedPassword);
        $staff2->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff2);

        $manager->flush();
    }
}

