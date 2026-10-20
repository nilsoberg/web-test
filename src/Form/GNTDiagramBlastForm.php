<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GNTDiagramBlastForm extends GNTDiagramForm
{
	use Partial\BlastParametersTrait, Partial\BlastSequenceTrait, Partial\ExcludeFragmentsTrait, Partial\UnirefVersionTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['show_blast_params'] = $options['show_blast_params'] ?? true;
        $view->vars['max_blast_sequences'] = $options['max_blast_sequences'];
        $view->vars['default_blast_sequences'] = $options['default_blast_sequences'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->addBlastParametersFields($builder, $options);
        $this->addBlastSequenceField($builder, $options);
        $this->addExcludeFragmentsField($builder, $options);
        $this->addUnirefVersionField($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\GNTDiagramBlastJob::class,
            'attr' => [],
            'show_blast_params' => true,
            'max_blast_sequences' => \App\Config\Constants::DEFAULT_MAX_GND_BLAST_SEQUENCES,
            'default_blast_sequences' => \App\Config\Constants::DEFAULT_GND_BLAST_SEQUENCES,
        ]);
    }
}
