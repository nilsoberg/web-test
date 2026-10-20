<?php

namespace App\Form;

use App\Entity\DatabaseMetadata;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class JobForm extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['default_email_address'] = $options['default_email_address'] ?? false;
        $view->vars['active_databases'] = $options['active_databases'] ?? null;
        $view->vars['show_dev_features'] = $options['show_dev_features'] ?? null;
        $view->vars['show_active_databases'] = $options['show_active_databases'] ?? true;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $emailFieldAttributes = [];
        if ($options['lock_email_address']) {
            $emailFieldAttributes['readonly'] = 'readonly';
            $emailFieldAttributes['class'] = 'bg-light text-muted';
        }

        $builder->add('email', EmailType::class, [
            'label' => false,
            'data' => $options['default_email_address'] ?? '',
            'mapped' => false,
            'required' => true,
            'attr' => $emailFieldAttributes,
        ]);

        $builder->get('email')->addModelTransformer(new Transformer\EmailToLowerCaseTransformer());

        if ($options['show_active_databases'] && isset($options['active_databases']) && count($options['active_databases']) > 1) {
            $builder->add('dbMetadata', EntityType::class, [
                'class' => DatabaseMetadata::class,
                'choices' => $options['active_databases'],
                'choice_label' => 'publicName',
                'label' => 'Choose Database Version',
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'novalidate' => 'novalidate',
                'spellcheck' => 'false',
                'autocomplete' => 'off',
            ],
            'default_email_address' => false,
            'lock_email_address' => false,
            'active_databases' => null,
            'show_dev_features' => false,
            'show_active_databases' => true,
        ]);
        $resolver->setAllowedTypes('lock_email_address', 'bool');
        $resolver->setAllowedTypes('show_dev_features', 'bool');
        $resolver->setAllowedTypes('show_active_databases', 'bool');
    }
}
