<?php

namespace App\Form\Partial;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait BlastParametersTrait
{
    public function addBlastParametersFields(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('maxBlastSequences', IntegerType::class, [
                'label' => false,
                'required' => false,
                'data' => $options['default_blast_sequences'] ?? \App\Config\Constants::DEFAULT_MAX_BLAST_SEQUENCES,
                'constraints' => [
                    new Assert\Range(
                        min: 100,
                        max: $options['max_blast_sequences'] ?? \App\Config\Constants::DEFAULT_MAX_ALL_BY_ALL_BLAST_SEQUENCES,
                        notInRangeMessage: 'Value must be between {{ min }} and {{ max }}.',
                    ),
                ],
            ])
            ->add('blastEvalue', IntegerType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'If specified, this value must be >= 0.',
                    ),
                ],
            ]);
    }
}
