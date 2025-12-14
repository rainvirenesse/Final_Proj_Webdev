<?php

namespace App\Form;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CustomerOrderType extends AbstractType
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isCompleted = $options['is_completed'] ?? false;
        $isNew = $options['is_new'] ?? false;
        
        // Get active products and services from options
        $activeProducts = $options['active_products'] ?? [];
        $activeServices = $options['active_services'] ?? [];
        
        // Get client users (users with ROLE_USER only, not admin or staff)
        $clientUsers = $this->userRepository->findClientUsers();
        
        $builder
            ->add('client', EntityType::class, [
                'class' => User::class,
                'choices' => $clientUsers,
                'choice_label' => 'email',
                'attr' => ['class' => 'form-control'],
                'label' => 'Client',
                'placeholder' => 'Select a client...',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Client must be selected.']),
                    new Assert\Callback([
                        'callback' => function($value, ExecutionContextInterface $context) {
                            if ($value && $value instanceof User && $value->getStatus() !== User::STATUS_ACTIVE) {
                                $context->buildViolation('Cannot assign an order to a disabled client. Only active clients can receive orders.')
                                    ->atPath('client')
                                    ->addViolation();
                            }
                        }
                    ])
                ]
            ])
            ->add('orderNumber', TextType::class, [
                'required' => false,
                'mapped' => false, // Don't map to entity, we'll generate it in controller
                'attr' => ['style' => 'display: none;'],
                'label' => false,
            ]);
        
        // Only add status and paymentStatus fields when editing (not new orders)
        if (!$isNew) {
            // Always include all status options - JavaScript will handle showing/hiding COMPLETED dynamically
            $builder
                ->add('status', ChoiceType::class, [
                    'choices' => [
                        'Pending' => CustomerOrder::STATUS_PENDING,
                        'In Progress' => CustomerOrder::STATUS_IN_PROGRESS,
                        'Completed' => CustomerOrder::STATUS_COMPLETED,
                        'Cancelled' => CustomerOrder::STATUS_CANCELLED,
                    ],
                    'attr' => [
                        'class' => 'form-control',
                        'disabled' => $isCompleted,
                        'id' => 'order-status-select'
                    ],
                    'label' => 'Status',
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Order status is required.']),
                        new Assert\Choice([
                            'choices' => [
                                CustomerOrder::STATUS_PENDING,
                                CustomerOrder::STATUS_IN_PROGRESS,
                                CustomerOrder::STATUS_COMPLETED,
                                CustomerOrder::STATUS_CANCELLED
                            ],
                            'message' => 'Invalid order status.'
                        ]),
                        new Assert\Callback([
                            'callback' => function($value, ExecutionContextInterface $context) {
                                $form = $context->getRoot();
                                $order = $form->getData();
                                if ($value === CustomerOrder::STATUS_COMPLETED && $order && $order->getPaymentStatus() !== CustomerOrder::PAYMENT_PAID) {
                                    $context->buildViolation('Cannot complete an order that is not fully paid.')
                                        ->atPath('status')
                                        ->addViolation();
                                }
                            }
                        ])
                    ]
                ]);
        }
        
        // Products and services are always editable
        $builder
            ->add('selectedProducts', EntityType::class, [
                'class' => Product::class,
                'choices' => $activeProducts,
                'choice_label' => function(Product $product) {
                    return $product->getName() . ' - ₱' . number_format((float)$product->getPrice(), 2);
                },
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control product-select',
                    'data-price-field' => 'product'
                ],
                'label' => 'Select Products',
                'placeholder' => 'Choose products...'
            ])
            ->add('selectedServices', EntityType::class, [
                'class' => Service::class,
                'choices' => $activeServices,
                'choice_label' => function(Service $service) {
                    return $service->getName() . ' - ₱' . number_format((float)$service->getPrice(), 2);
                },
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control service-select',
                    'data-price-field' => 'service'
                ],
                'label' => 'Select Services',
                'placeholder' => 'Choose services...'
            ])
            ->add('totalPrice', MoneyType::class, [
                'currency' => 'PHP',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                    'id' => 'total-price-input'
                ],
                'label' => 'Total Price (₱)',
                'disabled' => false, // Keep enabled but readonly for form submission
                'data' => '0.00', // Default value
                'constraints' => [
                    new Assert\NotNull(['message' => 'Total price cannot be null.']),
                    new Assert\PositiveOrZero(['message' => 'Total price must be zero or positive.'])
                ]
            ]);
        
        // Only add paymentStatus field when editing (not new orders)
        if (!$isNew) {
            $builder
                ->add('paymentStatus', ChoiceType::class, [
                    'choices' => [
                        'Unpaid' => CustomerOrder::PAYMENT_UNPAID,
                        'Paid' => CustomerOrder::PAYMENT_PAID,
                        'Partially Paid' => CustomerOrder::PAYMENT_PARTIALLY_PAID,
                    ],
                    'attr' => ['class' => 'form-control'],
                    'label' => 'Payment Status',
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Payment status is required.']),
                        new Assert\Choice([
                            'choices' => [
                                CustomerOrder::PAYMENT_UNPAID,
                                CustomerOrder::PAYMENT_PAID,
                                CustomerOrder::PAYMENT_PARTIALLY_PAID
                            ],
                            'message' => 'Invalid payment status.'
                        ])
                    ]
                ]);
        }
        
        $builder
            ->add('orderedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d\TH:i')
                ],
                'label' => 'Order Date',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Order date is required.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'Order date cannot be before the current date.'
                    ])
                ]
            ])
            ->add('completedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'completed-at-input'
                ],
                'label' => 'Completed Date',
                'disabled' => $isCompleted,
                'constraints' => [
                    new Assert\Callback([
                        'callback' => function($value, ExecutionContextInterface $context) {
                            $form = $context->getRoot();
                            $orderedAt = $form->get('orderedAt')->getData();
                            
                            if ($value !== null && $orderedAt !== null) {
                                // Convert DateTimeImmutable to DateTime for comparison if needed
                                $completedDate = $value instanceof \DateTimeImmutable 
                                    ? new \DateTime($value->format('Y-m-d H:i:s'))
                                    : $value;
                                $orderDate = $orderedAt instanceof \DateTimeImmutable
                                    ? new \DateTime($orderedAt->format('Y-m-d H:i:s'))
                                    : $orderedAt;
                                
                                if ($completedDate < $orderDate) {
                                    $context->buildViolation('Completed date cannot be before the order date.')
                                        ->atPath('completedAt')
                                        ->addViolation();
                                }
                            }
                        }
                    ])
                ]
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'label' => 'Notes',
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Notes cannot exceed {{ limit }} characters.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerOrder::class,
            'is_completed' => false,
            'is_new' => false,
            'active_products' => [],
            'active_services' => [],
        ]);
    }
}
