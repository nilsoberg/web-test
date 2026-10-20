<?php

namespace App\Form\Partial;

use App\Enum\SequenceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait UnirefVersionTrait
{
    public function addUnirefVersionField(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unirefVersion', EnumType::class, [
                'label' => false,
                'class' => SequenceType::class,
                'required' => true,
                'placeholder' => false,
                'data' => $options['default_uniref_version'] ?? null,
            ]);
    }
}
