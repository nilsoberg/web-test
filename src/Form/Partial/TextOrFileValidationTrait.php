<?php

namespace App\Form\Partial;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait TextOrFileValidationTrait
{
    protected function configureValidationOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'error_message_no_input',
            'error_message_both_inputs',
        ]);
    }

    public function validateSubmissionFields(mixed $data, ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        $options = $form->getConfig()->getOptions();

        $pastedTextUniprot = $form->get('textUniprot')->getData();
        $uploadedFileUniprot = $form->get('fileUniprot')->getData();

        $submissionCount = 0;
        if (!empty($pastedTextUniprot)) $submissionCount++;
        if ($uploadedFileUniprot instanceof UploadedFile) $submissionCount++;

        if ($submissionCount === 0) {
            $context
                ->buildViolation($options['error_message_no_input'])
                ->atPath('textUniprot')
                ->addViolation();
            return;
        }

        if ($submissionCount > 1) {
            $context
                ->buildViolation($options['error_message_both_inputs'])
                ->atPath('textUniprot')
                ->addViolation();
        }
    }
}
