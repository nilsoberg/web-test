<?php 

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class GNTDiagramSequenceIdForm extends GNTDiagramForm
{
    use Partial\ExcludeFragmentsTrait, Partial\TextOrFileValidationTrait, Partial\UnirefVersionTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['show_blast_params'] = $options['show_blast_params'] ?? false;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('fileUniprot', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a text file containing IDs to upload',
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
                Constants::FILE_EXTENSION => 'txt',
            ])
            ->add('textUniprot', Type\ProcessingAwareTextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'rows' => 10,
                ],
                'mapped' => false,
                Constants::PROCESSING_FLAG => Constants::STRING_TO_FILE,
                Constants::FILE_EXTENSION => 'txt',
            ]);

        $this->addExcludeFragmentsField($builder, $options);
        $this->addUnirefVersionField($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\GNTDiagramSequenceIdJob::class,
            'show_blast_params' => false,
            'constraints' => [new Callback([$this, 'validateSubmissionFields'])],
            'error_message_no_input' => 'Please provide one of: pasted UniProt IDs or UniProt ID file.',
            'error_message_both_inputs' => 'Please provide input in only one field, not multiple.',
        ]);
    }
}
