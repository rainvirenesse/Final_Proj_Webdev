<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProductImageFormHandler
{
    public function __construct(
        private readonly ProductImageStorage $storage,
    ) {
    }

    public function handle(FormInterface $form, Product $product): void
    {
        if (!$form->has('imageFile')) {
            return;
        }

        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            $previous = $product->getImage();
            $product->setImage($this->storage->store($file));
            $this->storage->removeIfManaged($previous);

            return;
        }

        if ($form->has('removeImage') && $form->get('removeImage')->getData() === true) {
            $this->storage->removeIfManaged($product->getImage());
            $product->setImage(null);
        }
    }
}
