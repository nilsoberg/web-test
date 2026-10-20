<?php

namespace App\Form;

use App\Config\Constants;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TaxonomyFamiliesForm extends TaxonomyForm
{
    use Partial\ExcludeFragmentsTrait, Partial\FamilyInputTrait, Partial\SequenceLengthTrait, Partial\TaxonomyFilterTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $this->buildFamilyFormView($view, $form, $options);
    }

    /**
     * This method adds the shared fields to the form builder.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->addSequenceLengthFields($builder, $options);
        $this->addExcludeFragmentsField($builder, $options);
        $this->addFamilyFormUi($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $this->configureFamilyFormOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\TaxonomyFamiliesJob::class,
            'require_family' => true,
        ]);
    }
}
