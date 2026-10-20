<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class PasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraints = [
            new Length(
                min: 10,
                minMessage: 'Your password should be at least {{ limit }} characters. Please use a stronger password.',
                max: 4096,
            ),
            new PasswordStrength(
                message: 'Your password is too weak. Please use a stronger password.',
            ),
        ];

        // ONLY add the NotBlank constraint if it's a new user
        if ($options['require_password']) {
            $constraints[] = new NotBlank(
                message: 'Please enter a password.',
            );
        }

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'first_options' => [
                    'label' => 'Password',
                    'help' => !$options['require_password'] ? 'Leave blank to keep the current password.' : '',
                    'required' => $options['require_password'],
                    'constraints' => $constraints,
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                    'required' => $options['require_password'],
                ],
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'require_password' => true,
        ]);
    }
}
