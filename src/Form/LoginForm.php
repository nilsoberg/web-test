<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class, [
                'label' => 'E-mail:',
                'required' => true,
                'attr' => [
                    'autofocus' => true,
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Password:',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Sign in',
                'attr' => [
                    'class' => 'login-button'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id'   => 'authenticate',
        ]);
    }

    /**
     * The name of the form should be empty, otherwise fields will be namespaced
     * under the form name (e.g., login[_username]), which breaks the security component.
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
