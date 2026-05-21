<?php

namespace App\Twig;

use App\Service\ProductImageUrlResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductImageUrlResolver $resolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_image_url', [$this, 'imageUrl']),
            new TwigFunction('product_image_path', [$this, 'imagePath']),
        ];
    }

    public function imageUrl(?string $stored): ?string
    {
        return $this->resolver->resolveAbsoluteUrl($stored);
    }

    public function imagePath(?string $stored): ?string
    {
        return $this->resolver->resolvePublicPath($stored);
    }
}
