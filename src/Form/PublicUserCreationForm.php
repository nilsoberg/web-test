<?php

namespace App\Form;

use App\Dto\PublicUserCreationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicUserCreationForm extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class)
            ->add('email', EmailType::class)
            ->add('timezone', TimezoneType::class, [
                'input' => 'string',
                'label' => 'Timezone',
            ])
            ->add('passwordFields', Type\PasswordFormType::class, [
                'mapped' => false,
                'label' => false,
                'require_password' => true,
            ]);

        $builder->get('email')
            ->addModelTransformer(new Transformer\EmailToLowerCaseTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'novalidate' => 'novalidate',
                'spellcheck' => 'false',
            ],
            'data_class' => PublicUserCreationData::class,
        ]);
    }
}
