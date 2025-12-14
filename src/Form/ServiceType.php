<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'] ?? false;
        
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Service Name',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Service name cannot be blank.']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Service name cannot exceed {{ limit }} characters.'
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
            ->add('durationInHours', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Duration (Hours)',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Duration is required.']),
                    new Assert\Positive(['message' => 'Duration must be positive.'])
                ]
            ])
            ->add('revisions', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Revisions',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Revisions must be set.']),
                    new Assert\PositiveOrZero(['message' => 'Revisions must be zero or positive.'])
                ]
            ]);
        
        // Only show status field when editing (not when creating new)
        if (!$isNew) {
            $builder->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => Service::STATUS_ACTIVE,
                    'Inactive' => Service::STATUS_INACTIVE,
                    'Completed' => Service::STATUS_COMPLETED,
                ],
                'attr' => ['class' => 'form-control'],
                'label' => 'Status',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Status cannot be blank.']),
                    new Assert\Choice([
                        'choices' => [Service::STATUS_ACTIVE, Service::STATUS_INACTIVE, Service::STATUS_COMPLETED],
                        'message' => 'Invalid status.'
                    ])
                ]
            ]);
        }
        
        // Add event listeners to set default values for new services
        if ($isNew) {
            // PRE_SET_DATA: Set defaults when form is first created
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $service = $event->getData();
                if ($service instanceof Service) {
                    if ($service->getStatus() === null) {
                        $service->setStatus(Service::STATUS_ACTIVE);
                    }
                    if ($service->getRevisions() === null) {
                        $service->setRevisions(0);
                    }
                }
            });
            
            // PRE_SUBMIT: Set defaults before data binding (in case they get cleared)
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();
                $form = $event->getForm();
                $service = $form->getData();
                
                if ($service instanceof Service) {
                    // Ensure status is set before binding
                    if ($service->getStatus() === null) {
                        $service->setStatus(Service::STATUS_ACTIVE);
                    }
                    if ($service->getRevisions() === null) {
                        $service->setRevisions(0);
                    }
                }
            });
            
            // SUBMIT: Set defaults after data binding but before validation
            $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                $service = $event->getData();
                if ($service instanceof Service) {
                    // Set default status if not set
                    if ($service->getStatus() === null) {
                        $service->setStatus(Service::STATUS_ACTIVE);
                    }
                    // Set default revisions if not set
                    if ($service->getRevisions() === null) {
                        $service->setRevisions(0);
                    }
                }
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
            'is_new' => false,
            'validation_groups' => function ($form) {
                $isNew = $form->getConfig()->getOption('is_new');
                return $isNew ? ['Default'] : ['Default', 'edit'];
            },
        ]);
    }
}
