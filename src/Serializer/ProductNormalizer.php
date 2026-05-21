<?php

namespace App\Serializer;

use App\Entity\Product;
use App\Service\ProductImageUrlResolver;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ProductNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'product_image_normalizer_called';

    public function __construct(
        private readonly ProductImageUrlResolver $imageUrlResolver,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize($object, $format, $context);

        if ($object instanceof Product) {
            $publicPath = $this->imageUrlResolver->resolvePublicPath($object->getImage());
            $data['imagePath'] = $publicPath;
            $data['imageUrl'] = $this->imageUrlResolver->resolveAbsoluteUrl($object->getImage());
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Product && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Product::class => false,
        ];
    }
}
