<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ESTGenerateBlastForm extends ESTGenerateForm
{
    use Partial\BlastParametersTrait, Partial\BlastSequenceTrait, Partial\FamilyInputTrait, Partial\ExcludeFragmentsTrait, Partial\TaxonomyFilterTrait, Partial\UnirefVersionTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['show_blast_params'] = $options['show_blast_params'] ?? true;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->addBlastParametersFields($builder, $options);
        $this->addBlastSequenceField($builder, $options);
        $this->addExcludeFragmentsField($builder, $options);
        $this->addFamilyFormUi($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
        $this->addUnirefVersionField($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $this->configureFamilyFormOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTGenerateBlastJob::class,
            'show_blast_params' => true,
        ]);
    }
}
