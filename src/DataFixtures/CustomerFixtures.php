<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $customers = [
            [
                'email' => 'john.doe@example.com',
                'username' => 'johndoe',
                'password' => 'customer123',
            ],
            [
                'email' => 'sarah.smith@example.com',
                'username' => 'sarahsmith',
                'password' => 'customer123',
            ],
            [
                'email' => 'michael.johnson@example.com',
                'username' => 'michaelj',
                'password' => 'customer123',
            ],
            [
                'email' => 'emily.brown@example.com',
                'username' => 'emilyb',
                'password' => 'customer123',
            ],
            [
                'email' => 'david.wilson@example.com',
                'username' => 'davidw',
                'password' => 'customer123',
            ],
            [
                'email' => 'lisa.anderson@example.com',
                'username' => 'lisaa',
                'password' => 'customer123',
            ],
            [
                'email' => 'robert.taylor@example.com',
                'username' => 'robertt',
                'password' => 'customer123',
            ],
            [
                'email' => 'jennifer.martinez@example.com',
                'username' => 'jenniferm',
                'password' => 'customer123',
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = new User();
            $customer->setEmail($customerData['email']);
            $customer->setUsername($customerData['username']);
            $customer->setRoles(['ROLE_CUSTOMER']);
            $customer->setIsVerified(true);
            $customer->setStatus(User::STATUS_ACTIVE);
            $hashedPassword = $this->passwordHasher->hashPassword($customer, $customerData['password']);
            $customer->setPassword($hashedPassword);
            $customer->setCreatedAt(new \DateTimeImmutable());
            
            $manager->persist($customer);
            $this->addReference('customer_' . $customerData['username'], $customer);
        }

        $manager->flush();
    }
}

