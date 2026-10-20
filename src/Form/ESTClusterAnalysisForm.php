<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ESTClusterAnalysisForm extends JobForm
{
    use Partial\ConditionalParentOrFileTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $pidChoices = range(10, 90, 10);
    
        $builder
            ->add('maxNumSequences', IntegerType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\LessThan(
                        value: 1001,
                        message: 'Maximum number of sequences must be <= 1,000.',
                    ),
                ],
            ])
            ->add('minNumSequences', IntegerType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThan(
                        value: 2,
                        message: 'Minimum number of sequences must be >= 3.',
                    ),
                ],
            ])
            ->add('makeWeblogo', CheckboxType::class, [
                'label' => 'Make WebLogos for each cluster',
                'required' => false,
            ])
            ->add('computeConsensusResidues', CheckboxType::class, [
                'label' => '',
                'required' => false,
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('makeHmms', CheckboxType::class, [
                'label' => 'Make HMMs for each cluster',
                'required' => false,
            ])
            ->add('makeLengthHistograms', CheckboxType::class, [
                'label' => 'Make length histograms for each cluster',
                'required' => false,
            ])
            ->add('consensusResidues', TextType::class, [
                'label' => 'Residues to compute for (comma-separated list of amino acid codes):',
                'required' => false,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^([A-Z](,[A-Z])*|)$/i',
                        message: 'The field can only contain amino acid residue codes, separated by commas.',
                    ),
                ],
            ])
            ->add('pidThresholds', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => array_combine($pidChoices, $pidChoices),
                'multiple' => true,
                'expanded' => true,
                'data' => $pidChoices,
                'attr' => ['class' => 'checkbox-group-inputs'],
                'row_attr' => ['class' => 'form-row-checkbox-group'],
            ]);

        $this->addConditionalFileOrParentFields($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTClusterAnalysisJob::class,
            'constraints' => [
                new Assert\Callback([$this, 'validateSubmissionFields']),
            ],
        ]);
        $this->configureParentSharedDefaults($resolver);
    }

    public function validateSubmissionFields(mixed $data, ExecutionContextInterface $context): void
    {
        $computeConsensus = $data->getComputeConsensusResidues();
        $consensusResidues = $data->getConsensusResidues();

        if ($computeConsensus === true && empty($consensusResidues)) {
            $context->buildViolation('"Consensus Residues" cannot be empty when "Compute Consensus Residues" is checked.')
                ->atPath('consensusResidues')
                ->addViolation();
        }
    }
}
