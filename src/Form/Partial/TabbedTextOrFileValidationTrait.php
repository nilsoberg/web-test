<?php

namespace App\Form\Partial;

use App\Enum\SequenceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait TabbedTextOrFileValidationTrait
{
    protected function configureValidationOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'error_message_no_input',
            'error_message_both_inputs',
        ]);
    }

    public function addPostSubmit(FormBuilderInterface $builder): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'onPostSubmit']);
    }

    public function onPostSubmit(FormEvent $event): void
    {
        $job = $event->getData();
        $form = $event->getForm();

        if (!$form->isValid()) {
            return;
        }

        // Check if data from the UniProt tab was submitted
        $uniprotText = $form->get('textUniprot')->getData();
        $uniprotFile = $form->get('fileUniprot')->getData();
        $unirefText = $form->get('textUniref')->getData();
        $unirefFile = $form->get('fileUniref')->getData();

        // Check if data was submitted from the UniProt tab.  This entity field has already been handled
		// by the symfony form logic, so we need to override it if the UniProt IDs tab was used
        if (!empty($uniprotText) || $uniprotFile !== null) {
            // Data came from UniProt tab
            $job->setUnirefVersion(SequenceType::UniProt);
        } elseif (!empty($unirefText) || $unirefFile !== null) {
            // Data came from UniRef tab, so set the unirefVersion to the selected version in the form
            $unirefVersion = $form->get('unirefVersion')->getData();
            $job->setUnirefVersion($unirefVersion);
        }
    }

    public function validateSubmissionFields(mixed $data, ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        $options = $form->getConfig()->getOptions();

        $pastedTextUniprot = $form->get('textUniprot')->getData();
        $uploadedFileUniprot = $form->get('fileUniprot')->getData();
        $pastedTextUniref = $form->get('textUniref')->getData();
        $uploadedFileUniref = $form->get('fileUniref')->getData();

        $submissionCount = 0;
        if (!empty($pastedTextUniprot)) $submissionCount++;
        if ($uploadedFileUniprot instanceof UploadedFile) $submissionCount++;
        if (!empty($pastedTextUniref)) $submissionCount++;
        if ($uploadedFileUniref instanceof UploadedFile) $submissionCount++;

        if ($submissionCount === 0) {
            $context
                ->buildViolation($options['error_message_no_input']) // Use the custom option
                ->atPath('textUniprot')
                ->addViolation();
            return;
        }

        if ($submissionCount > 1) {
            $context
                ->buildViolation($options['error_message_both_inputs']) // Use the custom option
                ->atPath('textUniprot')
                ->addViolation();
        }
    }
}
