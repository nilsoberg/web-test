<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ESTGenerateFamiliesForm extends ESTGenerateForm
{
    use Partial\DomainInputTrait, Partial\ExcludeFragmentsTrait, Partial\FamilyInputTrait, Partial\TaxonomyFilterTrait, Partial\UnirefVersionTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['exclude_domain_family'] = $options['exclude_domain_family'] ?? false;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->addDomainInputUi($builder, $options);
        $this->addExcludeFragmentsField($builder, $options);
        $this->addFamilyFormUi($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureDomainInputOptions($resolver);
        $this->configureFamilyFormOptions($resolver);

        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTGenerateFamiliesJob::class,
            'exclude_domain_family' => true,
            'require_family' => true,
        ]);
    }
}
