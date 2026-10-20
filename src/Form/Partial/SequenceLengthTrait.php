<?php

namespace App\Form\Partial;

use App\Config\Constants;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait SequenceLengthTrait
{
    public function addSequenceLengthFields(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minLength', IntegerType::class, [
                'label' => 'Minimum Length:',
                'required' => false,
                'attr' => [
                    'placeholder' => Constants::DEFAULT_MIN_LENGTH,
                ],
                'constraints' => [
                    new Assert\GreaterThanOrEqual(
                        value: Constants::DEFAULT_MIN_LENGTH,
                        message: 'Minimum length must be >= ' . Constants::DEFAULT_MIN_LENGTH,
                    ),
                ],
            ])
            ->add('maxLength', IntegerType::class, [
                'label' => 'Maximum Length:',
                'required' => false,
                'attr' => [
                    'placeholder' => Constants::DEFAULT_MAX_LENGTH,
                ],
                'constraints' => [
                    new Assert\LessThanOrEqual(
                        value: Constants::DEFAULT_MAX_LENGTH,
                        message: 'Maximum length must be <= ' . Constants::DEFAULT_MAX_LENGTH,
                    ),
                ],
            ]);
    }
}
