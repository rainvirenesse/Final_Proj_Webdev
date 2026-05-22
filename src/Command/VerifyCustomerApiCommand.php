<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:api:verify-customer',
    description: 'Smoke-test Customer API endpoints for demo / grading verification',
)]
final class VerifyCustomerApiCommand extends Command
{
    private const CUSTOMER_EMAIL = 'john.doe@example.com';
    private const CUSTOMER_PASSWORD = 'customer123';

    protected function configure(): void
    {
        $this
            ->addOption('base-url', 'b', InputOption::VALUE_REQUIRED, 'API base URL', 'http://127.0.0.1:8000')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Customer email', self::CUSTOMER_EMAIL)
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Customer password', self::CUSTOMER_PASSWORD);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = rtrim((string) $input->getOption('base-url'), '/');
        $client = HttpClient::create(['timeout' => 15]);

        $io->title('Customer API verification');
        $io->text(sprintf('Base URL: %s', $baseUrl));

        $passed = 0;
        $failed = 0;
        $productId = null;
        $token = null;
        $orderId = null;

        // 1. Products
        $r = $this->request($client, 'GET', $baseUrl.'/api/products');
        if ($this->assert($io, 'GET /api/products → 200', $r['status'] === 200 && ($r['json']['status'] ?? null) === 'success')) {
            ++$passed;
            $data = $r['json']['data'] ?? [];
            if (\is_array($data) && $data !== []) {
                foreach ($data as $p) {
                    if (($p['inStock'] ?? false) === true && isset($p['id'])) {
                        $productId = (int) $p['id'];
                        break;
                    }
                }
                $productId ??= (int) ($data[0]['id'] ?? 0);
            }
            $io->text(sprintf('  → Using productId: %s', $productId ?: 'NONE'));
        } else {
            ++$failed;
        }

        // 2. Login
        $r = $this->request($client, 'POST', $baseUrl.'/api/login', [
            'email' => $input->getOption('email'),
            'password' => $input->getOption('password'),
        ]);
        $token = $r['json']['token'] ?? $r['json']['data']['token'] ?? null;
        if ($this->assert($io, 'POST /api/login → 200 + token', $r['status'] === 200 && \is_string($token) && $token !== '')) {
            ++$passed;
        } else {
            ++$failed;
            $io->warning('Skipping authenticated tests (login failed). Run fixtures or verify customer email/password.');
            $this->summary($io, $passed, $failed);

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $auth = ['Authorization' => 'Bearer '.$token];

        // 3. Profile
        $r = $this->request($client, 'GET', $baseUrl.'/api/customer/profile', null, $auth);
        if ($this->assert($io, 'GET /api/customer/profile → 200', $r['status'] === 200)) {
            ++$passed;
        } else {
            ++$failed;
        }

        if (!$productId) {
            $io->warning('No in-stock product found — skipping cart/order/payment tests.');
            $this->summary($io, $passed, $failed);

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // 4. Add to cart
        $r = $this->request($client, 'POST', $baseUrl.'/api/cart/items', [
            'productId' => $productId,
            'quantity' => 1,
        ], $auth);
        if ($this->assert($io, 'POST /api/cart/items → 201', $r['status'] === 201)) {
            ++$passed;
        } else {
            ++$failed;
        }

        // 5. Get cart
        $r = $this->request($client, 'GET', $baseUrl.'/api/cart', null, $auth);
        if ($this->assert($io, 'GET /api/cart → 200', $r['status'] === 200)) {
            ++$passed;
        } else {
            ++$failed;
        }

        // 6. Place order
        $r = $this->request($client, 'POST', $baseUrl.'/api/orders', ['notes' => 'API verify command'], $auth);
        $orderId = $r['json']['data']['id'] ?? null;
        if ($this->assert($io, 'POST /api/orders → 201', $r['status'] === 201 && $orderId !== null)) {
            ++$passed;
        } else {
            ++$failed;
        }

        if ($orderId) {
            // 7. Payment
            $r = $this->request($client, 'POST', $baseUrl.'/api/payments', [
                'orderId' => $orderId,
                'method' => 'verify',
                'reference' => 'VERIFY-'.time(),
            ], $auth);
            if ($this->assert($io, 'POST /api/payments → 201', $r['status'] === 201)) {
                ++$passed;
            } else {
                ++$failed;
            }

            // 8. List orders
            $r = $this->request($client, 'GET', $baseUrl.'/api/customer/orders', null, $auth);
            if ($this->assert($io, 'GET /api/customer/orders → 200', $r['status'] === 200)) {
                ++$passed;
            } else {
                ++$failed;
            }
        }

        $this->summary($io, $passed, $failed);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     *
     * @return array{status: int, json: array<string, mixed>, raw: string}
     */
    private function request(
        HttpClientInterface $client,
        string $method,
        string $url,
        ?array $body = null,
        array $headers = [],
    ): array {
        $options = [
            'headers' => array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $headers),
        ];
        if ($body !== null) {
            $options['body'] = json_encode($body, \JSON_THROW_ON_ERROR);
        }

        try {
            $response = $client->request($method, $url, $options);
            $raw = $response->getContent(false);
            $json = json_decode($raw, true);

            return [
                'status' => $response->getStatusCode(),
                'json' => \is_array($json) ? $json : [],
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'json' => ['error' => $e->getMessage()],
                'raw' => $e->getMessage(),
            ];
        }
    }

    private function assert(SymfonyStyle $io, string $label, bool $ok): bool
    {
        if ($ok) {
            $io->writeln(sprintf('  <fg=green>✓</> %s', $label));
        } else {
            $io->writeln(sprintf('  <fg=red>✗</> %s', $label));
        }

        return $ok;
    }

    private function summary(SymfonyStyle $io, int $passed, int $failed): void
    {
        $io->newLine();
        if ($failed === 0) {
            $io->success(sprintf('All %d check(s) passed. Ready for demo.', $passed));
        } else {
            $io->warning(sprintf('%d passed, %d failed. See README.md troubleshooting.', $passed, $failed));
        }
    }
}
