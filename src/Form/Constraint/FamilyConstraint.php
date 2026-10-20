<?php

namespace App\Form\Constraint;

use Symfony\Component\Validator\Constraints\Regex;

trait FamilyConstraint
{
    public function getFamilyConstraint(string $fieldName = ''): Regex
    {
        $constraint = new Regex(
               pattern: '/^[A-Z0-9\s,]+$/i',
               message: "The $fieldName field can only contain letters, numbers, spaces, and commas.",
           );
        return $constraint;
    }
}
