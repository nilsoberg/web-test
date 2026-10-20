<?php

namespace App\Form\Constraint;

use Symfony\Component\Validator\Constraints as Assert;

trait ThresholdValueConstraint
{
    public function getThresholdValueConstraint(): array
    {
        $constraints = [];
        $constraints[] = new Assert\NotBlank(
            message: 'Please enter a threshold value.'
        );
        $constraints[] = new Assert\GreaterThan(
            value: -1,
            message: 'Threshold value must be >= 0.'
        );
        return $constraints;
    }
}
