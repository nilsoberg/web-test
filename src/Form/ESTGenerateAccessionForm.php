<?php

namespace App\Form;

use App\Enum\SequenceType;
use App\Form\Type\DomainInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ESTGenerateAccessionForm extends ESTGenerateForm
{
    use Constraint\FamilyConstraint, Partial\DomainInputTrait, Partial\ExcludeFragmentsTrait, Partial\FamilyInputTrait, Partial\TabbedTextOrFileValidationTrait, Partial\TaxonomyFilterTrait, Partial\UnirefVersionTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('fileUniprot', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a text file containing UniProt IDs to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'txt',
            ])
            ->add('textUniprot', Type\ProcessingAwareTextareaType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 10,
                ],
                Constants::PROCESSING_FLAG => Constants::STRING_TO_FILE,
                Constants::FILE_EXTENSION => 'txt',
            ])
            ->add('fileUniref', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a text file containing UniRef IDs to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'txt',
            ])
            ->add('textUniref', Type\ProcessingAwareTextareaType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 10,
                ],
                Constants::PROCESSING_FLAG => Constants::STRING_TO_FILE,
                Constants::FILE_EXTENSION => 'txt',
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

        // Add handlers for uploading or pasted files/IDs
        $this->addPostSubmit($builder);

        $builder->add('familyFilter', TextType::class, [
                'label' => false,
                'required' => false,
                'property_path' => 'filterByFamilies', // This maps the form element name to the entity property
                'constraints' => [
                    $this->getFamilyConstraint('Family Filter'),
                ],
            ]);

        $this->addDomainInputUi($builder, $options);
        $this->addExcludeFragmentsField($builder, $options);
        $this->addFamilyFormUi($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureDomainInputOptions($resolver);
        $this->configureFamilyFormOptions($resolver);
        $this->configureValidationOptions($resolver);

        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTGenerateAccessionJob::class,
            'exclude_domain_family' => false,
            'constraints' => [new Callback([$this, 'validateSubmissionFields'])],
            'error_message_no_input' => 'Please provide one of: pasted UniProt IDs, UniProt ID file, pasted UniRef IDs, or UniRef ID file.',
            'error_message_both_inputs' => 'Please provide input in only one field, not multiple.',
        ]);
    }
}
