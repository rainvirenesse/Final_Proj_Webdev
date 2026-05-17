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
        $admin->setEmail('raincredo91@gmail.com');
        $admin->setUsername('rain');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'rain123');
        $admin->setPassword($hashedPassword);
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($admin);

        // Create Staff User 1
        $staff1 = new User();
        $staff1->setEmail('rainvirenesse@gmail.com');
        $staff1->setUsername('tristan');
        $staff1->setRoles(['ROLE_STAFF']);
        $staff1->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($staff1, 'rain123');
        $staff1->setPassword($hashedPassword);
        $staff1->setIsVerified(true);
        $staff1->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff1);

        $staff2 = new User();
        $staff2->setEmail('reannjeanie@gmail.com');
        $staff2->setUsername('jeanie');
        $staff2->setRoles(['ROLE_STAFF']);
        $staff2->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($staff2, 'jinirian123');
        $staff2->setPassword($hashedPassword);
        $staff2->setIsVerified(true);
        $staff2->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff2);
        // Create Staff User 2
        // $staff2 = new User();
        // $staff2->setEmail('staff2@example.com');
        // $staff2->setUsername('darl');
        // $staff2->setRoles(['ROLE_STAFF']);
        // $staff2->setStatus(User::STATUS_ACTIVE);
        // $hashedPassword = $this->passwordHasher->hashPassword($staff2, 'rain123');
        // $staff2->setPassword($hashedPassword);
        // $staff2->setCreatedAt(new \DateTimeImmutable());
        // $manager->persist($staff2);
        $staff3 = new User();
        $staff3->setEmail('vcarrie69@gmail.com.com');
        $staff3->setUsername('carrie');
        $staff3->setRoles(['ROLE_STAFF']);
        $staff3->setStatus(User::STATUS_ACTIVE);
        $hashedPassword = $this->passwordHasher->hashPassword($staff3, 'karidada123');
        $staff3->setPassword($hashedPassword);
        $staff3->setIsVerified(true);
        $staff3->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($staff3);

        $manager->flush();
    }
}

