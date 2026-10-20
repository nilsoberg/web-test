<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Permits us to have an optional default email field field on every field without requiring to
 * be explicitly defined or not provided
 */
class DefaultEmailExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        // Make this global to all forms
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Allow the option to be passed/not passed without error, but isn't required
        $resolver->setDefined('default_email_address');
        
        // Must be a string
        $resolver->setAllowedTypes('default_email_address', ['string', 'null']);
    }
}
