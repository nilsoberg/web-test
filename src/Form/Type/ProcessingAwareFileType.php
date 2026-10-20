<?php

namespace App\Form\Type;

use App\Form\Constants;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessingAwareFileType extends FileType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

    	$resolver->setDefaults([
            'attr' => [
                'data-controller' => 'ux-dropzone',
                'placeholder' => 'Drag and drop a file or click to browse',
            ],
        ]);

        $resolver->setDefined([
            Constants::PROCESSING_FLAG,
            Constants::FILE_EXTENSION,
        ]);
    }

    public function getBlockPrefix(): string { return 'dropzone'; }
}
