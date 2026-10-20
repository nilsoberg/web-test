<?php

namespace App\Form\Partial;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

trait ExcludeFragmentsTrait
{
    public function addExcludeFragmentsField(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('excludeFragments', CheckboxType::class, [
                'label' => 'Check to exclude UniProt-defined fragments in the results. [default: off]',
                'required' => false,
                'block_prefix' => 'exclude_fragments',
            ]);
    }
}
