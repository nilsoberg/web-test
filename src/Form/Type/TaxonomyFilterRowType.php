<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TaxonomyFilterRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $rankChoices = [
            'Superkingdom' => 'domain',
            'Kingdom' => 'kingdom',
            'Phylum' => 'phylum',
            'Class' => 'class',
            'Order' => 'tax_order',
            'Family' => 'family',
            'Genus' => 'genus',
            'Species' => 'species',
        ];

        $builder
            ->add('field', ChoiceType::class, [
                'label' => false,
                'choices' => $rankChoices,
                'attr' => [
                    'class' => 'taxonomy-rank-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Please select a rank.'
                    ),
                    new Assert\Choice(
                        choices: array_values($rankChoices),
                        message: 'Please provide a valid rank.'
                    )
                ],
            ])
            ->add('operator', ChoiceType::class, [
                'choices' => [
                    'Is'               => '=',
                    'Is Not'           => '!=',
                    'Contains'         => 'LIKE',
                    'Does Not Contain' => 'NOT LIKE',
                ],
                'attr' => ['class' => 'taxonomy-operator-select']
            ])
            ->add('value', TextType::class, [
                'label' => false,
                'attr' => [
                    'class' => 'taxonomy-value-input',
                    'placeholder' => 'Start typing to search',
                    'data-action' => 'input->taxonomy-filter#autocomplete',
                    'autocomplete' => 'do-not-autofill', // Prevent autocompletion from the brwoser
                    'autocorrect' => 'off',
                    'autocapitalize' => 'off',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Please provide a value.'
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z\s\-\.]+$/',
                        message: 'The field can only contain words.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
