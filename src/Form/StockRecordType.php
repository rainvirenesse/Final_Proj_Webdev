<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\StockRecord;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class StockRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => fn (Product $p) => sprintf('#%d — %s (on hand: %d)', $p->getId(), $p->getName(), $p->getStock()),
                'placeholder' => 'Select a product',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'label' => 'Product',
                'query_builder' => function (ProductRepository $repository) {
                    return $repository->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC');
                },
                'disabled' => $options['product_locked'],
            ])
            ->add('quantityDelta', IntegerType::class, [
                'label' => 'Quantity change',
                'help' => 'Use positive numbers to add stock, negative numbers to remove stock.',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(['message' => 'Quantity change is required.']),
                    new Assert\NotEqualTo(['value' => 0, 'message' => 'Quantity change cannot be zero.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockRecord::class,
            'product_locked' => false,
        ]);
        $resolver->setAllowedTypes('product_locked', 'bool');
    }
}
