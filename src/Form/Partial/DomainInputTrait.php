<?php

namespace App\Form\Partial;

use App\Form\Type\DomainInputType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/*
 * A helper class for adding the FamilyInputType form group to forms to share code.
 */
trait DomainInputTrait
{
    public function buildDomainInputFormView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['exclude_domain_family'] = $options['exclude_domain_family'] ?? false;
    }

    /**
     * This method adds the shared fields to the form builder.
     */
    public function addDomainInputUi(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('domainInput', DomainInputType::class, [
            'label' => false,
            'inherit_data' => true,
            'exclude_domain_family' => $options['exclude_domain_family'],
        ]);
    }

    public function configureDomainInputOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'exclude_domain_family' => false,
        ]);
        $resolver->setAllowedTypes('exclude_domain_family', 'bool');
    }
}
