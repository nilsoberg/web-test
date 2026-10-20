<?php

namespace App\Form;

use App\Entity\DatabaseMetadata;
use App\Enum\CGFPSearchType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CGFPQuantifyForm extends AbstractType
{
    use Partial\JobNameTrait;

    public function __construct(
    )
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['default_ssn_name'] = $options['default_ssn_name'] ?? '';
        $view->vars['parent_id'] = $options['parent_id'] ?? null;
        $view->vars['parent_key'] = $options['parent_key'] ?? null;
        $view->vars['active_databases'] = $options['active_databases'] ?? null;
        $view->vars['show_dev_features'] = $options['show_dev_features'] ?? null;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('parent_reference', Type\ParentJobReferenceType::class, [
                'parent_subtype_required' => false,
                'parent_id' => $options['parent_id'] ?? null,
                'parent_key' => $options['parent_key'] ?? null,
                'parent_required' => true,
            ])
            ->add('quantifySearchType', EnumType::class, [
                'label' => false,
                'class' => CGFPSearchType::class,
                'required' => true,
                'placeholder' => false,
                'choice_label' => fn(CGFPSearchType $choice) => $choice->getLabel(),
            ])
            ->add('metagenomeDatabase', EntityType::class, [
                'class' => DatabaseMetadata::class,
                'choices' => $options['active_databases'],
                'choice_label' => 'publicName',
                'label' => 'Choose Metagenome Database',
                'required' => true,
                'placeholder' => 'Choose a Metagenome Database',
            ])
            ->add('metagenomes', HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'The list of metagenome IDs cannot be blank.'),
                ],
            ]);

        $this->addJobNameField($builder, $options, true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
            'data_class' => \App\Entity\CGFPQuantifyJob::class,
            'default_ssn_name' => '',
            'parent_id' => null,
            'parent_key' => null,
            'show_dev_features' => false,
            'active_databases' => null,
        ]);

        $resolver->setAllowedTypes('show_dev_features', 'bool');
    }
}
