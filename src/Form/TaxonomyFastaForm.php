<?php 

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TaxonomyFastaForm extends TaxonomyForm
{
    use Partial\TextOrFileValidationTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('textUniprot', Type\ProcessingAwareTextareaType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 10,
                ],
                Constants::PROCESSING_FLAG => Constants::STRING_TO_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ])
            ->add('fileUniprot', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a FASTA file containing IDs and sequences to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureValidationOptions($resolver);

        $resolver->setDefaults([
            'data_class' => \App\Entity\TaxonomyFastaJob::class,
            'constraints' => [
                new Callback([$this, 'validateSubmissionFields']), // validateSubmissionFields is in the validation trait
            ],
            'error_message_no_input' => 'Please provide one of: pasted sequences with UniProt ID headers or a FASTA file with UniProt ID headers.',
            'error_message_both_inputs' => 'Please provide input in only one field, not multiple.',
        ]);
    }
}
