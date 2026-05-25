<?php

namespace App\Controller;

use App\Service\ShopCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class LandingPageController extends AbstractController
{
    /** Minimum trimmed characters for the contact message (heading is not the message). */
    private const CONTACT_MESSAGE_MIN_LEN = 3;

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private ShopCatalog $shopCatalog,
        private string $contactFormEmbedUrl,
    ) {
    }

    #[Route('/', name: 'app_root')]
    public function root(): RedirectResponse
    {
        return $this->redirectToRoute('landing');
    }

    #[Route('/LandingPage', name: 'landing')]
    public function index(): Response
    {
        return $this->render('landing_page/index.html.twig', [
            'about_lead' => $this->getAboutLead(),
            'about_body' => $this->getAboutBody(),
            'collections' => $this->shopCatalog->getCollections(),
            'products' => $this->shopCatalog->getFeaturedProducts(),
            'testimonials' => $this->getTestimonials(),
            'team' => $this->getTeam(),
            'faqs' => $this->getFaqs(),
        ]);
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('landing_page/about.html.twig', [
            'about_lead' => $this->getAboutLead(),
            'about_body' => $this->getAboutBody(),
            'about_extra_paragraphs' => $this->getAboutExtraParagraphs(),
            'team' => $this->getTeam(),
        ]);
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contactPage(): Response
    {
        $embedUrl = $this->normalizeContactEmbedUrl($this->contactFormEmbedUrl);

        return $this->render('landing_page/contact.html.twig', [
            'contactFormEmbedUrl' => $embedUrl,
            'contactFormOpenUrl' => $embedUrl !== null ? $this->deriveContactFormOpenUrl($embedUrl) : null,
        ]);
    }

    #[Route('/LandingPage/products', name: 'landing_products', methods: ['GET'])]
    public function productsPage(): Response
    {
        return $this->render('landing_page/products.html.twig', [
            'collections' => $this->shopCatalog->getCollections(),
            'products' => $this->shopCatalog->getAllProducts(),
        ]);
    }

    #[Route('/LandingPage/contact', name: 'landing_contact_submit', methods: ['POST'])]
    public function landingContactSubmit(Request $request): RedirectResponse
    {
        return $this->handleContactForm($request, 'landing', 'faq');
    }

    #[Route('/contact/submit', name: 'contact_submit', methods: ['POST'])]
    public function contactSubmit(Request $request): RedirectResponse
    {
        return $this->handleContactForm($request, 'contact');
    }

    #[Route('/LandingPage/subscribe', name: 'landing_subscribe_submit', methods: ['POST'])]
    public function subscribe(Request $request): RedirectResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));

        if ($name === '' || mb_strlen($name) < 2) {
            $this->addFlash('error', 'Please enter your name (at least 2 characters).');
            return $this->redirectToRoute('landing', ['_fragment' => 'subscribe']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');
            return $this->redirectToRoute('landing', ['_fragment' => 'subscribe']);
        }

        $from = new Address('raincredo91@gmail.com', 'R A I N');
        $adminTo = new Address('raincredo91@gmail.com', 'R A I N');
        $subscriberTo = new Address($email, $name);

        $adminHtml = '
            <p>New subscription from the RAIN landing page.</p>
            <p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
        ';
        $adminText = "New subscription from the RAIN landing page.\n\nName: {$name}\nEmail: {$email}\n";

        $welcomeHtml = '
            <p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>Thanks for subscribing to <strong>RAIN</strong>. Here is your welcome treat: use code <strong>RAINWELCOME</strong> on your next order for a special discount (details will follow in future emails).</p>
            <p>We are glad you are here.</p>
        ';
        $welcomeText = "Hi {$name},\n\nThanks for subscribing to RAIN. Welcome code: RAINWELCOME — use it on your next order for a special discount.\n";

        $sentAdmin = false;
        $sentSubscriber = false;
        $mailError = null;

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($adminTo)
                    ->replyTo($subscriberTo)
                    ->subject('[RAIN] New newsletter subscription')
                    ->html($adminHtml)
                    ->text($adminText)
            );
            $sentAdmin = true;
        } catch (\Throwable $e) {
            $mailError ??= $e;
            $this->logger->error('Landing subscribe admin email failed', [
                'name' => $name,
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($subscriberTo)
                    ->subject('You are subscribed — RAIN')
                    ->html($welcomeHtml)
                    ->text($welcomeText)
            );
            $sentSubscriber = true;
        } catch (\Throwable $e) {
            $mailError ??= $e;
            $this->logger->error('Landing subscribe welcome email failed', [
                'name' => $name,
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        if ($sentAdmin && $sentSubscriber) {
            $this->addFlash('success', "You are in, {$name}! Check your inbox for a welcome note and discount code.");
        } elseif ($sentAdmin) {
            $this->addFlash('success', "We saved your subscription. If you do not see our email, check spam or promotions.");
        } elseif ($sentSubscriber) {
            $this->addFlash('success', "Welcome email sent! We will follow up from raincredo91@gmail.com shortly.");
        } else {
            $this->addFlash('error', $this->mailFailureMessageForUser($mailError));
        }

        return $this->redirectToRoute('landing', ['_fragment' => 'subscribe']);
    }

    private function handleContactForm(Request $request, string $redirectRoute, ?string $fragment = null): RedirectResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $message = trim((string) $request->request->get('message', ''));

        if ($name === '' || mb_strlen($name) < 2) {
            $this->addFlash('error', 'Please enter your name (at least 2 characters).');
            return $this->redirectForContactFailure($redirectRoute, $fragment);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');
            return $this->redirectForContactFailure($redirectRoute, $fragment);
        }

        if (mb_strlen($message) < self::CONTACT_MESSAGE_MIN_LEN) {
            $this->addFlash(
                'error',
                'Please type a short message in the Message box (the line under Name and Email — not only the section title). At least '
                . self::CONTACT_MESSAGE_MIN_LEN . ' characters.'
            );
            return $this->redirectForContactFailure($redirectRoute, $fragment);
        }

        $from = new Address('raincredo91@gmail.com', 'R A I N');
        $adminTo = new Address('raincredo91@gmail.com', 'R A I N');
        $replyTo = new Address($email, $name);

        $htmlBody = '
            <p>You have received a new contact message from your RAIN landing page.</p>
            <p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Message:</strong><br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>
        ';

        $textBody = "You have received a new contact message from your RAIN landing page.\n\n"
            . "Name: {$name}\n"
            . "Email: {$email}\n\n"
            . "Message:\n{$message}\n";

        $userHtml = '
            <p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>Thanks for reaching out to <strong>RAIN</strong>. We received your message and will get back to you soon.</p>
            <p>In the meantime, you can explore our collection on the homepage.</p>
        ';

        $userText = "Hi {$name},\n\nThanks for reaching out to RAIN. We received your message and will get back to you soon.\n";

        $sentUser = false;
        $sentAdmin = false;
        $mailError = null;

        try {
            $confirmation = (new Email())
                ->from($from)
                ->to($replyTo)
                ->subject('Thanks for contacting RAIN')
                ->html($userHtml)
                ->text($userText);

            $this->mailer->send($confirmation);
            $sentUser = true;
        } catch (\Throwable $e) {
            $mailError ??= $e;
            $this->logger->error('Landing contact confirmation email failed', [
                'name' => $name,
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        try {
            $emailMessage = (new Email())
                ->from($from)
                ->to($adminTo)
                ->replyTo($replyTo)
                ->subject('New message from landing page (contact)')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($emailMessage);
            $sentAdmin = true;
        } catch (\Throwable $e) {
            $mailError ??= $e;
            $this->logger->error('Landing contact admin email failed', [
                'name' => $name,
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        if ($sentUser && $sentAdmin) {
            $this->addFlash('success', "Thanks {$name}! Your message has been sent. We'll get back to you soon.");
        } elseif ($sentUser) {
            $this->addFlash('success', "Thanks {$name}! We received your message. Our team will review it shortly.");
        } elseif ($sentAdmin) {
            $this->addFlash('success', "We received your message at raincredo91@gmail.com. If you do not get a confirmation email, check spam or try another address.");
        } else {
            $this->addFlash('error', $this->mailFailureMessageForUser($mailError));
        }

        $params = [];
        if ($fragment !== null && $fragment !== '') {
            $params['_fragment'] = $fragment;
        }

        return $this->redirectToRoute($redirectRoute, $params);
    }

    private function redirectForContactFailure(string $redirectRoute, ?string $fragment): RedirectResponse
    {
        $params = [];
        if ($fragment !== null && $fragment !== '') {
            $params['_fragment'] = $fragment;
        }

        return $this->redirectToRoute($redirectRoute, $params);
    }

    /** @return list<string> */
    private function getAboutExtraParagraphs(): array
    {
        return [
            'Each style is developed with a careful balance of silhouette, materials, and walkability — so you can move from work meetings to dinner without a wardrobe change. We work closely with trusted workshops, inspect pairs before dispatch, and refine lasts and straps based on real feedback from customers across Luzon, Visayas, and Mindanao.',
            'Nationwide shipping is core to how we serve the RAIN community. Orders are packed with care and tracked with our courier partners; sizing guidance and honest product photos help you choose confidently before you check out. For boutiques, influencers, or corporate gifting, we also welcome collaboration and wholesale inquiries tailored to your audience.',
            'Looking ahead, we continue to invest in versatile capsules — neutrals you can dress up, statement accents that still feel wearable, and small drops that reward subscribers first. Our team reads every message and review; your steps shape the next collection.',
        ];
    }

    private function getAboutLead(): string
    {
        return 'RAIN exists to inspire confidence and self-expression through footwear.';
    }

    private function getAboutBody(): string
    {
        return 'We create stylish, comfortable, and versatile shoes that blend everyday practicality with modern fashion trends. Our mission is to make every step a statement — empowering individuals to walk boldly, wherever life takes them.';
    }

    /** @return list<array{name: string, role: string, image: string, bio: string}> */
    private function getTeam(): array
    {
        return [
            ['name' => 'Rain Virenesse Credo', 'role' => 'Brand & Design Lead', 'image' => 'reen.png', 'bio' => 'Shapes the look, feel, and details that make every pair uniquely RAIN.'],
            ['name' => 'Jeanie Reann', 'role' => 'Product & Fit Specialist', 'image' => 'jini.png', 'bio' => 'Ensures comfort-first construction with a polished, wearable finish.'],
            ['name' => 'Carrie Victoria', 'role' => 'Customer Experience', 'image' => 'kari.png', 'bio' => 'Helps you find the right style with care, clarity, and quick support.'],
            ['name' => 'Tristan Frank', 'role' => 'Growth & Partnerships', 'image' => 'franc.png', 'bio' => 'Builds collaborations and community initiatives that keep RAIN moving forward.'],
        ];
    }

    /** @return list<array{quote: string, author: string}> */
    private function getTestimonials(): array
    {
        return [
            ['quote' => 'Always happy with my purchase! The packaging is beautiful and the shoes are even more stunning in person.', 'author' => 'Maria Santos'],
            ['quote' => 'Exactly as described. The quality is perfect — so pretty yet so comfortable. Highly recommended!', 'author' => 'Trisha Mae'],
            ['quote' => 'I love this brand so much. I always want to come back and shop more. The styles are just perfect for my wardrobe.', 'author' => 'Camille Reyes'],
        ];
    }

    /** @return list<array{question: string, answer: string}> */
    private function getFaqs(): array
    {
        return [
            ['question' => 'What sizes do you carry?', 'answer' => 'We carry sizes 35–42 (Philippine standard). Size charts are available on each product page.'],
            ['question' => 'Do you accept returns and exchanges?', 'answer' => 'Yes! We accept returns within 7 days of receipt, provided items are unused and in original packaging.'],
            ['question' => 'How long does shipping take?', 'answer' => 'Metro Manila: 2–3 business days. Provincial: 5–7 business days via partner couriers.'],
            ['question' => 'Do you offer customization or bulk orders?', 'answer' => 'Yes! Contact us at business@rain.ph for wholesale and customization inquiries.'],
            ['question' => 'Are your shoes true to size?', 'answer' => 'Most styles are true to size. For narrow or wide feet, we recommend sizing up.'],
        ];
    }

    private function normalizeContactEmbedUrl(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '') {
            return null;
        }

        if (!str_starts_with($url, 'https://')) {
            $this->logger->warning('CONTACT_FORM_EMBED_URL ignored: must be an https URL.');

            return null;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return null;
        }

        $allowed =
            str_ends_with($host, 'google.com')
            || str_ends_with($host, 'sibforms.com');

        if (!$allowed) {
            $this->logger->warning('CONTACT_FORM_EMBED_URL ignored: host not allowed for embed.', ['host' => $host]);

            return null;
        }

        return $url;
    }

    private function deriveContactFormOpenUrl(string $embedUrl): string
    {
        if (!str_contains($embedUrl, 'docs.google.com/forms')) {
            return $embedUrl;
        }

        $parts = parse_url($embedUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $embedUrl;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['embedded']);

        $path = $parts['path'] ?? '';
        $q = http_build_query($query);

        return $parts['scheme'] . '://' . $parts['host'] . $path . ($q !== '' ? '?' . $q : '');
    }

    private function mailFailureMessageForUser(?\Throwable $e): string
    {
        if ($e === null) {
            return 'We could not send email. Please try again in a moment.';
        }

        $m = $e->getMessage();
        if (str_contains($m, '535') || str_contains($m, 'Authentication failed')) {
            return 'Mail login failed. In Brevo, open SMTP & API and copy the exact SMTP login and key into MAILER_DSN (use brevo+smtp://…@default).';
        }

        return 'We could not send email. Please try again shortly.';
    }
}
