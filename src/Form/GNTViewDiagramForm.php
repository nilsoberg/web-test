<?php 

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class GNTViewDiagramForm extends JobForm
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('gndFile', Type\ProcessingAwareFileType::class, [
                'label' => false,
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Select a GND sqlite or zip file to upload',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Please select a file to upload.',
                    ),
                    new File(
                        extensions: ['zip', 'sqlite'],
                        extensionsMessage: 'Please upload a valid GND file (e.g., .sqlite or .zip).',
                        uploadErrorMessage: 'There was an error uploading your GND file. Please try again.',
                    ),
                ],
                Constants::PROCESSING_FLAG => Constants::UPLOADED_FILE,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\GNTViewDiagramJob::class,
        ]);
    }
}
