<?php

namespace App\DataFixtures;

use App\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ServiceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $services = [
            [
                'name' => 'Custom Shoe Design',
                'description' => 'Personalized shoe design service where our expert designers work with you to create a unique pair of shoes tailored to your preferences, style, and measurements.',
                'price' => 850.00,
                'durationInHours' => 72,
                'revisions' => 3,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Premium Shoe Repair',
                'description' => 'Professional shoe repair service including sole replacement, heel repair, stitching, and leather restoration. Restore your favorite shoes to like-new condition.',
                'price' => 75.00,
                'durationInHours' => 24,
                'revisions' => 1,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Shoe Resoling Service',
                'description' => 'Expert resoling service using premium materials. Extend the life of your shoes with professional sole replacement.',
                'price' => 120.00,
                'durationInHours' => 48,
                'revisions' => 1,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Leather Conditioning & Care',
                'description' => 'Deep conditioning and care treatment for leather shoes to maintain their quality, appearance, and longevity.',
                'price' => 45.00,
                'durationInHours' => 12,
                'revisions' => 1,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Shoe Stretching Service',
                'description' => 'Professional shoe stretching service to ensure the perfect fit. Ideal for shoes that are slightly too tight.',
                'price' => 35.00,
                'durationInHours' => 24,
                'revisions' => 1,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Color Matching & Dyeing',
                'description' => 'Expert color matching and professional shoe dyeing service to change or restore the color of your shoes.',
                'price' => 95.00,
                'durationInHours' => 36,
                'revisions' => 2,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Orthotic Insole Customization',
                'description' => 'Custom orthotic insoles designed specifically for your feet to provide maximum comfort and support.',
                'price' => 150.00,
                'durationInHours' => 48,
                'revisions' => 2,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Express Shine & Polish',
                'description' => 'Quick professional shoe shine and polish service to keep your shoes looking their best.',
                'price' => 25.00,
                'durationInHours' => 2,
                'revisions' => 0,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Shoe Restoration Package',
                'description' => 'Comprehensive restoration package including cleaning, repair, resoling, and refinishing. Complete makeover for your shoes.',
                'price' => 200.00,
                'durationInHours' => 96,
                'revisions' => 2,
                'status' => Service::STATUS_ACTIVE,
            ],
            [
                'name' => 'Bespoke Shoe Consultation',
                'description' => 'One-on-one consultation with our master craftsman to discuss your bespoke shoe requirements and design preferences.',
                'price' => 150.00,
                'durationInHours' => 2,
                'revisions' => 1,
                'status' => Service::STATUS_ACTIVE,
            ],
        ];

        foreach ($services as $serviceData) {
            $service = new Service();
            $service->setName($serviceData['name']);
            $service->setDescription($serviceData['description']);
            $service->setPrice($serviceData['price']);
            $service->setDurationInHours($serviceData['durationInHours']);
            $service->setRevisions($serviceData['revisions']);
            $service->setStatus($serviceData['status']);
            // createdAt and updatedAt are set automatically via lifecycle callbacks
            
            $manager->persist($service);
        }

        $manager->flush();
    }
}

