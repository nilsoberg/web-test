<?php

namespace App\Form;

use App\Enum\SequenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ESTGenerateFastaForm extends ESTGenerateForm
{
    use Partial\FamilyInputTrait, Partial\TabbedTextOrFileValidationTrait, Partial\TaxonomyFilterTrait, Partial\UnirefVersionTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

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
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a FASTA file containing UniProt IDs and sequences to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ])
            ->add('textUniref', Type\ProcessingAwareTextareaType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 10,
                ],
                Constants::PROCESSING_FLAG => Constants::STRING_TO_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ])
            ->add('fileUniref', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Select a FASTA file containing UniRef IDs and sequences to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'fasta',
            ])
            ->add('unirefVersion', EnumType::class, [
                'label' => false,
                'class' => SequenceType::class,
                'mapped' => false, // Do internal mapping
                'choices' => [
                    'UniRef90 cluster IDs' => SequenceType::UniRef90,
                    'UniRef50 cluster IDs' => SequenceType::UniRef50,
                ],
            ]);

        $this->addFamilyFormUi($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
        $this->addPostSubmit($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureFamilyFormOptions($resolver);
        $this->configureValidationOptions($resolver);

        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTGenerateFastaJob::class,
            'constraints' => [
                new Callback([$this, 'validateSubmissionFields']), // validateSubmissionFields is in the validation trait
            ],
            'error_message_no_input' => 'Please provide one of: pasted sequences with UniProt ID headers, a FASTA file with UniProt ID headers, pasted sequences with UniRef ID headers, or a FASTA file with UniRef ID headers.',
            'error_message_both_inputs' => 'Please provide input in only one field, not multiple.',
        ]);
    }
}
