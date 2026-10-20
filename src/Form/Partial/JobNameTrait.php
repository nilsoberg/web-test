<?php

namespace App\Form\Partial;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait JobNameTrait
{
    public function addJobNameField(FormBuilderInterface $builder, array $options, bool $required = false): void
    {
        $options = [
            'label' => false,
            'required' => $required,
        ];

        if ($required) {
            $options['constraints'] = [new Assert\NotBlank(message: 'Job name is required')];
        }

        $builder->add('jobName', TextType::class, $options);
    }
}
