<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Turns stored product image values into web paths and absolute URLs for Twig + mobile API.
 */
final class ProductImageUrlResolver
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire('%app.url%')]
        private readonly string $appUrl,
    ) {
    }

    /**
     * Path relative to the public/ directory (e.g. images/products/foo.jpg).
     */
    public function resolvePublicPath(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $stored = trim($stored);

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $stored = ltrim($stored, '/');

        if (
            str_starts_with($stored, 'images/')
            || str_starts_with($stored, 'uploads/')
            || str_starts_with($stored, 'build/')
            || str_starts_with($stored, 'image/')
        ) {
            return $stored;
        }

        // Legacy: filename only (catalog assets).
        if (!str_contains($stored, '/')) {
            if ($this->publicAssetExists('build/images/'.$stored)) {
                return 'build/images/'.$stored;
            }
            if ($this->publicAssetExists('image/'.$stored)) {
                return 'image/'.$stored;
            }

            return 'build/images/'.$stored;
        }

        return $stored;
    }

    public function resolveAbsoluteUrl(?string $stored): ?string
    {
        $path = $this->resolvePublicPath($stored);
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim($this->resolveBaseUrl(), '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function resolveBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim($this->appUrl, '/');
    }

    private function publicAssetExists(string $relativePath): bool
    {
        $absolute = \dirname(__DIR__, 2).'/public/'.$relativePath;

        return is_file($absolute);
    }
}
