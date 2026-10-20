<?php

namespace App\Form\Partial;

use App\Form\Type\ParentJobReferenceType;
use App\Form\Type\ProcessingAwareFileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait ConditionalParentOrFileTrait
{
    public function addConditionalFileOrParentFields(FormBuilderInterface $builder, array $options): void
    {
        $isChildJob = !empty($options['parent_id']) && !empty($options['parent_key']);

        if ($isChildJob) {
            $builder->add('parent_reference', ParentJobReferenceType::class, [
                'parent_subtype_required' => $options['parent_subtype_required'] ?? true,
                'parent_id' => $options['parent_id'] ?? null,
                'parent_key' => $options['parent_key'] ?? null,
                'parent_subtype' => $options['parent_subtype'] ?? null,
                'parent_subtype_name' => $options['parent_subtype_name'] ?? null,
                'parent_required' => true,
                'parent_metadata' => $options['parent_metadata'] ?? null,
                'suggested_name' => $options['suggested_name'] ?? null,
            ]);
        } else {
            $builder->add('ssnFile', ProcessingAwareFileType::class, [
                'label' => false,
                'required' => !$isChildJob, // Only browser-required if no parent fallback exists
                'mapped' => false,
                'attr' => [
                    'placeholder' => ParentJobReferenceType::DEFAULT_SSN_FILE_PLACEHOLDER,
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Please select a file to upload',
                    ),
                    new Assert\File(
                        extensions: ['xml', 'zip', 'xgmml'],
                        extensionsMessage: 'Please upload a valid XGMML or Zip file',
                    ),
                ],
                \App\Form\Constants::PROCESSING_FLAG => \App\Form\Constants::UPLOADED_FILE,
            ]);
        }
    }

    public function configureParentSharedDefaults(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'parent_id' => null,
            'parent_key' => null,
            'parent_subtype' => null,
            'parent_subtype_name' => null,
            'parent_subtype_required' => true,
            'parent_metadata' => null,
            'suggested_name' => null,
        ]);
    }
}
