<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingPageController extends AbstractController
{
    #[Route('/LandingPage', name: 'landing')]
    public function index(): Response
    {
        return $this->render('landing_page/index.html.twig', [

            'about' => 'R A I N exists to inspire confidence and self-expression through
                        footwear. We create stylish, comfortable, and versatile shoes that
                        blend everyday practicality with modern fashion trends. Our mission
                        is to make every step a statement — empowering individuals to walk
                        boldly, wherever life takes them.',

            'collections' => [
                ['name' => 'Casual Chic Collection',    'subtitle' => 'Effortless everyday style',  'image' => 'collection-casual.jpg'],
                ['name' => 'Daily Comfort Collection',  'subtitle' => 'All-day wearability',        'image' => 'collection-comfort.jpg'],
                ['name' => 'Elegant Heels Collection',  'subtitle' => 'Refined sophistication',     'image' => 'collection-heels.jpg'],
                ['name' => 'Chic Streetwear Collection','subtitle' => 'Bold urban statements',      'image' => 'collection-street.jpg'],
            ],

            'products' => [
                ['name' => 'Two-Tone Pointed Stiletto Heels', 'description' => 'Elegant pointed heels with satin finish',    'price' => 2799,  'tag' => 'New',         'image' => 'product-1.jpg'],
                ['name' => 'Classic Deep Wine Pumps',         'description' => 'Timeless block-heeled silhouette',           'price' => 3199,  'tag' => null,          'image' => 'product-2.jpg'],
                ['name' => 'Low-Heel Mary Jane Flats',        'description' => 'Comfortable low-heel design',                'price' => 1899,  'tag' => 'Sale',        'image' => 'product-3.jpg'],
                ['name' => 'Slingback Bow Block Heels',       'description' => 'Refined slingback with bow detail',          'price' => 2499,  'tag' => null,          'image' => 'product-4.jpg'],
                ['name' => 'Braided Strap Slip-On Sandals',   'description' => 'Woven leather upper, cushioned sole',        'price' => 1699,  'tag' => null,          'image' => 'product-5.jpg'],
                ['name' => 'Knee-High Square Heel Boots',     'description' => 'Luxe suede finish, block heel',              'price' => 5299,  'tag' => 'Best Seller', 'image' => 'product-6.jpg'],
                ['name' => 'Platform Ankle-Strap High Heels', 'description' => 'Thick platform with ankle strap',           'price' => 3599,  'tag' => null,          'image' => 'product-7.jpg'],
                ['name' => 'Chain Accent Loafers',            'description' => 'Classic loafer with gold chain detail',      'price' => 2899,  'tag' => null,          'image' => 'product-8.jpg'],
            ],

            'testimonials' => [
                ['quote' => 'Always happy with my purchase! The packaging is beautiful and the shoes are even more stunning in person.',                     'author' => 'Maria Santos'],
                ['quote' => 'Exactly as described. The quality is perfect — so pretty yet so comfortable. Highly recommended!',                              'author' => 'Trisha Mae'],
                ['quote' => 'I love this brand so much. I always want to come back and shop more. The styles are just perfect for my wardrobe.',             'author' => 'Camille Reyes'],
            ],

            'faqs' => [
                ['question' => 'What sizes do you carry?',                       'answer' => 'We carry sizes 35–42 (Philippine standard). Size charts are available on each product page.'],
                ['question' => 'Do you accept returns and exchanges?',           'answer' => 'Yes! We accept returns within 7 days of receipt, provided items are unused and in original packaging.'],
                ['question' => 'How long does shipping take?',                   'answer' => 'Metro Manila: 2–3 business days. Provincial: 5–7 business days via partner couriers.'],
                ['question' => 'Do you offer customization or bulk orders?',     'answer' => 'Yes! Contact us at business@rain.ph for wholesale and customization inquiries.'],
                ['question' => 'Are your shoes true to size?',                   'answer' => 'Most styles are true to size. For narrow or wide feet, we recommend sizing up.'],
            ],
        ]);
    }
}