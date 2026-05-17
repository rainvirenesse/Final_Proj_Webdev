<?php

namespace App\Controller;

use App\Service\ShopCatalog;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ShopController extends AbstractController
{
    public function __construct(
        private ShopCatalog $shopCatalog,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/shop/product/{slug}', name: 'shop_product_show', methods: ['GET'])]
    public function productShow(string $slug): Response
    {
        $product = $this->shopCatalog->getProductBySlug($slug);
        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return $this->render('shop/product_show.html.twig', [
            'product' => $product,
            'shippingZones' => ShopCatalog::SHIPPING_ZONES,
        ]);
    }

    #[Route('/shop/checkout', name: 'shop_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('shop_checkout', $token))) {
            $this->addFlash('error', 'Invalid form token. Please try again.');

            return $this->redirectToRoute('landing_products');
        }

        $slug = strtolower(trim((string) $request->request->get('product_slug')));
        $product = $this->shopCatalog->getProductBySlug($slug);
        if ($product === null) {
            $this->addFlash('error', 'This product is no longer available.');

            return $this->redirectToRoute('landing_products');
        }

        $size = trim((string) $request->request->get('size'));
        $color = trim((string) $request->request->get('color'));
        $qty = (int) $request->request->get('quantity', 1);
        $zone = strtolower(trim((string) $request->request->get('shipping_zone')));
        $name = trim((string) $request->request->get('buyer_name'));
        $email = trim((string) $request->request->get('buyer_email'));
        $phone = trim((string) $request->request->get('buyer_phone'));
        $address = trim((string) $request->request->get('shipping_address'));
        $notes = trim((string) $request->request->get('order_notes'));

        if (!\in_array($size, $product['sizes'], true)) {
            $this->addFlash('error', 'Please choose a valid size.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        $colors = $product['colors'] ?? [];
        if ($colors !== [] && !\in_array($color, $colors, true)) {
            $this->addFlash('error', 'Please choose a valid color.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        if ($qty < 1 || $qty > 10) {
            $this->addFlash('error', 'Quantity must be between 1 and 10.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        if (!isset(ShopCatalog::SHIPPING_ZONES[$zone])) {
            $this->addFlash('error', 'Please select a shipping region.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }

        if ($name === '' || mb_strlen($name) < 2) {
            $this->addFlash('error', 'Please enter your full name.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        if ($phone === '' || mb_strlen($phone) < 8) {
            $this->addFlash('error', 'Please enter a valid phone number.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }
        if (mb_strlen($address) < 10) {
            $this->addFlash('error', 'Please enter a complete shipping address (at least 10 characters).');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }

        $unit = (float) $product['price'];
        $subtotal = $unit * $qty;
        $shipFee = (float) ShopCatalog::SHIPPING_ZONES[$zone]['fee'];
        $total = $subtotal + $shipFee;
        $zoneLabel = ShopCatalog::SHIPPING_ZONES[$zone]['label'];

        $esc = static fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $from = new Address('raincredo91@gmail.com', 'R A I N');
        $adminTo = new Address('raincredo91@gmail.com', 'R A I N');
        $replyTo = new Address($email, $name);

        $lines = [
            '<p><strong>New order request</strong> from the RAIN shop.</p>',
            '<p><strong>Product:</strong> ' . $esc((string) $product['name']) . '</p>',
            '<p><strong>Size:</strong> ' . $esc($size) . ' &nbsp; <strong>Color:</strong> ' . $esc($color ?: '—') . ' &nbsp; <strong>Qty:</strong> ' . $qty . '</p>',
            '<p><strong>Unit price:</strong> ₱' . number_format($unit, 2) . '<br>',
            '<strong>Subtotal:</strong> ₱' . number_format($subtotal, 2) . '<br>',
            '<strong>Shipping (' . $esc($zoneLabel) . '):</strong> ₱' . number_format($shipFee, 2) . '<br>',
            '<strong>Estimated total:</strong> ₱' . number_format($total, 2) . '</p>',
            '<p><strong>Customer:</strong> ' . $esc($name) . '<br>',
            '<strong>Email:</strong> ' . $esc($email) . '<br>',
            '<strong>Phone:</strong> ' . $esc($phone) . '</p>',
            '<p><strong>Ship to:</strong><br>' . nl2br($esc($address)) . '</p>',
        ];
        if ($notes !== '') {
            $lines[] = '<p><strong>Notes:</strong><br>' . nl2br($esc($notes)) . '</p>';
        }
        $lines[] = '<p><em>Reply to this email to confirm payment and fulfillment (COD, bank transfer, or e-wallet).</em></p>';

        $htmlBody = implode("\n", $lines);
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->to($adminTo)
                    ->replyTo($replyTo)
                    ->subject('RAIN shop — order: ' . $product['name'])
                    ->html($htmlBody)
                    ->text($textBody)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Shop checkout email failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->addFlash('error', 'We could not send your order right now. Please try again or contact us by phone.');

            return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
        }

        $this->addFlash(
            'success',
            'Order received! We emailed the details to our team. You will get payment and delivery instructions shortly.'
        );

        return $this->redirectToRoute('shop_product_show', ['slug' => $slug]);
    }
}
