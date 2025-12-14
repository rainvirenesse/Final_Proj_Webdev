<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Username cannot be blank.']),
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Username must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Username cannot exceed {{ limit }} characters.'
                    ]),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/^[a-zA-Z0-9_]+$/',
                        'message' => 'Username can only contain letters, numbers, and underscores.'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Email cannot be blank.']),
                    new \Symfony\Component\Validator\Constraints\Email(['message' => 'Please enter a valid email address.']),
                    new \Symfony\Component\Validator\Constraints\Length([
                        'max' => 180,
                        'maxMessage' => 'Email cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Profile Image',
                'mapped' => false, // Not directly mapped to entity (handle upload manually)
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file',
                    ])
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => $this->getRoleChoices($options),
                'multiple' => true,
                'expanded' => true, // checkboxes
                'required' => true,
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => $options['is_new'] ?? false, // required for new users
                'mapped' => false,   // handle password manually
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $options['is_new'] ? [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Password cannot be blank.']),
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.'
                    ])
                ] : []
            ]);
        
        // Only show status field when editing (not when creating new)
        $isNew = $options['is_new'] ?? false;
        if (!$isNew) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => User::STATUS_ACTIVE,
                    'Disabled' => User::STATUS_DISABLED,
                    'Archived' => User::STATUS_ARCHIVED,
                ],
                'attr' => ['class' => 'form-control'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
        ]);
    }
    
    private function getRoleChoices(array $options): array
    {
        // Admin role is never available through the form (must be set manually in database)
        $choices = [
            'User' => 'ROLE_USER',
            'Staff' => 'ROLE_STAFF',
        ];
        
        return $choices;
    }
}
