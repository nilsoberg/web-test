<?php

namespace App\Form\Partial;

use App\Form\Constants;
use App\Form\Type\ProcessingAwareTextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait BlastSequenceTrait
{
    public function addBlastSequenceField(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('blastSequence', ProcessingAwareTextareaType::class, [
                'label' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Please enter a BLAST sequence.'
                    ),
                    new Assert\Regex(
                	    // Explanation of pattern:
                        // ^                           Start of string
                        // (?:>[^\r\n]*[\r\n]+)?       Optional Header Group:
                        //      >                      Literal '>'
                        //      [^\r\n]* Any character EXCEPT newlines (allows numbers, '.', '_', etc.)
                        //      [\r\n]+                Must end with a newline
                        // [A-Z\-\s\*]+                Body: Only letters, whitespace, hyphens, or asterisks
                        // $                           End of string
                        // Allows:
                        //   Case 1:
                        //     >Header 123-._
                        //     MIL
                        //   Case 2:
                        //     >Header
                        //     MIL
                        //   Case 3:
                        //     MILK
                        //   Disallow:
                        //     >Header 123-._
                        //     MIL5
                        pattern: '/^(?:>[^\r\n]*[\r\n]+)?[A-Z\-\s\*]+$/i',
                        message: "The sequence can only contain amino acid codes (letters, gaps). Numbers and symbols are only allowed in the header line (starting with '>').",
                    ),
                ],
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'Paste a single sequence in FASTA format',
                ],
                Constants::PROCESSING_FLAG => Constants::BLAST_SEQ_TO_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ]);
    }
}
