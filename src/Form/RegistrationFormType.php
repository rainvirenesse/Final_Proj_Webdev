<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;


class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter your username'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Username cannot be blank.'),
                    new Assert\Length(
                        min: 3,
                        max: 50,
                        minMessage: 'Username must be at least {{ limit }} characters long.',
                        maxMessage: 'Username cannot exceed {{ limit }} characters.'
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'Username can only contain letters, numbers, and underscores.'
                    )
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter your email'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Email cannot be blank.'),
                    new Assert\Email(message: 'Please enter a valid email address.'),
                    new Assert\Length(
                        max: 180,
                        maxMessage: 'Email cannot exceed {{ limit }} characters.'
                    )
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter your password',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Password cannot be blank.'),
                    new Assert\Length(
                        min: 6,
                        minMessage: 'Password must be at least {{ limit }} characters long.'
                    )
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}