<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FeedbackForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Your Name',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter your name.',
                    ),
                    new Length(
                        min: 2,
                        minMessage: 'Your name must be at least {{ limit }} characters long.',
                    ),
                ],
                'attr' => [
                    'placeholder' => '',
                    'class' => 'form-control'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Your Email',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter your email address.'
                    ),
                    new Email(
                        message: 'The email "{{ value }}" is not a valid email.'
                    ),
                ],
                'attr' => [
                    'placeholder' => 'you@example.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('organization', TextType::class, [
                'label' => 'Your Organization',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Optional name of the organization you belong to',
                    'class' => 'form-control'
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Feedback',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter your feedback message.'
                    ),
                    new Length(
                        min: 10,
                        minMessage: 'Your feedback must be at least {{ limit }} characters long.'
                    ),
                ],
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Enter your message here...',
                    'class' => 'form-control'
                ]
            ])
            ->add('website_url', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                    'style' => 'position: absolute; left: -9999px; width: 1px; height: 1px;',
                ],
            ])
            // Submission timer check
            ->add('form_render_time', HiddenType::class, [
                'mapped' => false,
                'data' => time(),
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Send Feedback',
                'attr' => [
                    'class' => 'btn btn-primary mt-3'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // This allows the form to be processed without being tied to a specific Doctrine entity.
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
