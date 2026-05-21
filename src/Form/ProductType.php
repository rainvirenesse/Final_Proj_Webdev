<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Product Name',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Product name cannot be blank.']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Product name cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5],
                'label' => 'Description',
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('price', MoneyType::class, [
                'currency' => 'PHP',
                'attr' => ['class' => 'form-control'],
                'label' => 'Price (₱)',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Price is required.']),
                    new Assert\PositiveOrZero(['message' => 'Price must be zero or positive.'])
                ]
            ])
            ->add('stock', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 0],
                'label' => 'Stock',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Stock is required.']),
                    new Assert\PositiveOrZero(['message' => 'Stock must be zero or positive.']),
                ]
            ])
            ->add('category', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'Category',
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'Category cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => Product::STATUS_ACTIVE,
                    'Inactive' => Product::STATUS_INACTIVE,
                    'Out of Stock' => Product::STATUS_OUT_OF_STOCK,
                ],
                'attr' => ['class' => 'form-control'],
                'label' => 'Status'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Product image',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/jpeg,image/png,image/webp,image/gif'],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage' => 'Please upload a JPEG, PNG, WebP, or GIF image.',
                    ]),
                ],
            ]);

        if (!$options['is_new']) {
            $builder->add('removeImage', CheckboxType::class, [
                'label' => 'Remove current image',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'is_new' => false,
        ]);
    }
}

