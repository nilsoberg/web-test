<?php

namespace App\Form\Type;

use App\Form\Constants;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessingAwareTextareaType extends TextareaType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefined([
            Constants::PROCESSING_FLAG,
            Constants::FILE_EXTENSION,
        ]);
    }
}
