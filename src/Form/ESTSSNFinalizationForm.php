<?php

namespace App\Form;

use App\Config\Constants;
use App\Enum\ThresholdMetric;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ESTSSNFinalizationForm extends AbstractType
{
    use Constraint\ThresholdValueConstraint, Partial\ExcludeFragmentsTrait, Partial\SequenceLengthTrait, Partial\TaxonomyFilterTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['default_ssn_name'] = $options['default_ssn_name'] ?? '';
        $view->vars['parent_id'] = $options['parent_id'] ?? null;
        $view->vars['parent_key'] = $options['parent_key'] ?? null;
        $view->vars['active_databases'] = $options['active_databases'] ?? null;
        $view->vars['show_dev_features'] = $options['show_dev_features'] ?? null;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('thresholdValue', IntegerType::class, [
                'label' => false,
                'required' => true,
                'constraints' => $this->getThresholdValueConstraint(),
            ])
            ->add('thresholdMetric', EnumType::class, [
                'label' => false,
                'class' => ThresholdMetric::class,
                'required' => true,
                'placeholder' => false,
                'choice_label' => fn(ThresholdMetric $choice) => $choice->getLabel(),
            ])
            ->add('computeNeighborhoodConnectivity', CheckboxType::class, [
                'label' => 'Check to compute Neighborhood Connectivity (number of edges to other nodes) [default: off].',
                'required' => false,
            ])
            ->add('ssnName', TextType::class, [
                'label' => 'This name will be displayed in Cytoscape.',
                'required' => true,
                'property_path' => 'jobName',
                'data' => $options['default_ssn_name'] ?? '',
                'constraints' => [new Assert\NotBlank(
                    message: 'Please enter a SSN name.'
                )],
            ])
            ->add('parent_reference', Type\ParentJobReferenceType::class, [
                'parent_subtype_required' => false,
                'parent_id' => $options['parent_id'] ?? null,
                'parent_key' => $options['parent_key'] ?? null,
                'parent_required' => true,
            ]);

        if ($options['show_dev_features']) {
            $builder
                ->add('makeRepnodeNetworks', CheckboxType::class, [
                    'label' => 'Check to create repnode networks [default: on].',
                    'required' => false,
                ])
                ->add('maxSsnEdges', IntegerType::class, [
                    'label' => 'The maximum number of edges that are allowed when creating a SSN.',
                    'required' => false,
                ]);
        }

        $this->addExcludeFragmentsField($builder, $options);
        $this->addSequenceLengthFields($builder, $options);
        $this->addTaxonomyFilterUi($builder, $options, $this->urlGenerator);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
            'data_class' => \App\Entity\ESTSSNFinalizationJob::class,
            'default_ssn_name' => '',
            'parent_id' => null,
            'parent_key' => null,
            'show_dev_features' => false,
            'active_databases' => null,
            'constraints' => [
                new Assert\Callback([$this, 'validateSubmissionFields']),
            ],
        ]);

        $resolver->setAllowedTypes('show_dev_features', 'bool');
    }

    public function validateSubmissionFields(mixed $data, ExecutionContextInterface $context): void
    {
        $metric = $data->getThresholdMetric();
        $value = $data->getThresholdValue();

        // This case is handled by the other constraint on the thresholdMetric field
        if ($value === null || empty($value) || $value < 0) {
            return;
        }

        // Ensure that percent Identity is between 0 (other constraint) and 100, inclusive
        if ($metric === ThresholdMetric::PercentIdentity && $value > 100) {
            $context->buildViolation('"Threshold Value" cannot be greater than zero when "Threshold Metric" is "Percent Identity".')
                ->atPath('thresholdValue')
                ->addViolation();
        }
    }
}
