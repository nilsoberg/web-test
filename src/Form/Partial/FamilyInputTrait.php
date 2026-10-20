<?php

namespace App\Form\Partial;

use App\Form\Type\FamilyInputType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/*
 * A helper class for adding the FamilyInputType form group to forms to share code.
 */
trait FamilyInputTrait
{
    public function buildFamilyFormView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['render_fraction_outside_widget'] = $options['render_fraction_outside_widget'] ?? false;
        $view->vars['hide_fraction_widget'] = $options['hide_fraction_widget'] ?? false;
        $view->vars['show_sequence_type_in_family_widget'] = $options['show_sequence_type_in_family_widget'] ?? false;
    }

    /**
     * This method adds the shared fields to the form builder.
     */
    public function addFamilyFormUi(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('familyInput', FamilyInputType::class, [
            'label' => false,
            'include_fraction' => $options['render_fraction_in_family_widget'],
            'hide_fraction_widget' => $options['hide_fraction_widget'],
            'show_sequence_type_in_family_widget' => $options['show_sequence_type_in_family_widget'],
            'require_family' => $options['require_family'],
            'inherit_data' => true,
        ]);
    }

    public function configureFamilyFormOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
            'render_fraction_in_family_widget' => true,
            'hide_fraction_widget' => false,
            'show_sequence_type_in_family_widget' => false,
            'require_family' => false,
        ]);
    }
}
