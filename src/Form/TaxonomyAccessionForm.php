<?php 

namespace App\Form;

use App\Enum\SequenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TaxonomyAccessionForm extends TaxonomyForm
{
    use Partial\ExcludeFragmentsTrait, Partial\TabbedTextOrFileValidationTrait, Partial\TaxonomyFilterTrait, Constraint\FamilyConstraint;

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
            ->add('familyFilter', TextType::class, [
                'label' => false,
                'required' => false,
                'property_path' => 'filterByFamilies', // This maps the form element name to the entity property
                'constraints' => [
                    $this->getFamilyConstraint('Family Filter'),
                ],
            ])
            ->add('unirefVersion', EnumType::class, [
                'label' => false,
                'class' => SequenceType::class,
                'mapped' => false, // Do internal mapping
                'choices' => [
                    SequenceType::UniRef90,
                    SequenceType::UniRef50,
                ],
                'choice_label' => fn(SequenceType $choice) => match ($choice) {
                    SequenceType::UniRef90 => 'UniRef90 cluster IDs',
                    SequenceType::UniRef50 => 'UniRef50 cluster IDs',
                },
            ]);

        $this->addExcludeFragmentsField($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);

        $this->addPostSubmit($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureValidationOptions($resolver);

        $resolver->setDefaults([
            'data_class' => \App\Entity\TaxonomyAccessionJob::class,
            'constraints' => [
                new Callback([$this, 'validateSubmissionFields']), // validateSubmissionFields is in the validation trait
            ],
            'error_message_no_input' => 'Please provide one of: pasted UniProt IDs, UniProt ID file, pasted UniRef IDs, or UniRef ID file.',
            'error_message_both_inputs' => 'Please provide input in only one field, not multiple.',
        ]);
    }
}
