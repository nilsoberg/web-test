<?php

namespace App\Form\Type;

use App\Enum\SequenceType;
use App\Form\Transformer\FamilyIdToUpperCaseTransformer;
use App\Form\Transformer\FamilySeparatorTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A custom compound form type for the complex family input.  This handles the actual input
 * fields and groups them into a single, cohesive form element.  In contrast, the FamilyFormType
 * is a helper class that shares code for adding the FamilyInputType to a form.
 */
class FamilyInputType extends AbstractType
{
    use \App\Form\Constraint\FamilyConstraint, \App\Form\Partial\UnirefVersionTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['include_fraction'] = $options['include_fraction'] ?? false;
        $view->vars['hide_fraction_widget'] = $options['hide_fraction_widget'] ?? false;
        $view->vars['show_sequence_type_in_family_widget'] = $options['show_sequence_type_in_family_widget'] ?? false;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $required = $options['require_family'];
        $constraints = [
            $this->getFamilyConstraint('Family'),
        ];
        if ($required) {
            $constraints[] = new Assert\NotBlank(
                message: 'One or more families must be provided.'
            );
        }

        $builder->add('families', TextType::class, [
            'label' => false,
            'required' => false, // Constraints takes care of this
            'property_path' => 'addFamilies', // this maps the form element name to the entity property
            'attr' => [
                'placeholder' => 'Enter comma-separated family IDs',
            ],
            'constraints' => $constraints,
        ]);

        if ($options['show_sequence_type_in_family_widget']) {
            $builder->add('unirefVersion', EnumType::class, [
                'label' => false,
                'class' => SequenceType::class,
                'required' => true, // Required because the form element is always submitted
            ]);
        }

        $builder->get('families')
            ->addModelTransformer(new FamilySeparatorTransformer())
            ->addModelTransformer(new FamilyIdToUpperCaseTransformer());

        if (!$options['hide_fraction_widget']) {
            $fractionConstraint = new Assert\GreaterThan(
                value: 0,
                message: 'Fraction must be > 0.'
            );
            $builder->add('fraction', IntegerType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [$fractionConstraint],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'include_fraction' => false, // Default to rendering outside the widget
            'hide_fraction_widget' => false, // Default to including widget
            'show_sequence_type_in_family_widget' => false, // Default to hiding sequence type/database
            'require_family' => false, // Require family input
            'attr' => ['novalidate' => 'novalidate'],
        ]);
        $resolver->setAllowedTypes('include_fraction', 'bool');
        $resolver->setAllowedTypes('hide_fraction_widget', 'bool');
        $resolver->setAllowedTypes('show_sequence_type_in_family_widget', 'bool');
        $resolver->setAllowedTypes('require_family', 'bool');
    }
}
