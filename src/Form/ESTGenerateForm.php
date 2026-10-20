<?php

namespace App\Form;

use App\Config\Constants;
use App\Enum\AllByAllSearchSensitivity;
use App\Enum\AllByAllSearchType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

abstract class ESTGenerateForm extends JobForm
{
    use Partial\JobNameTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    /**
     * This method adds the shared fields to the form builder.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('allByAllBlastEValue', IntegerType::class, [
                'label' => false,
                'required' => false,
                'block_prefix' => 'ssn_edge_calc',
                'constraints' => [
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'BLAST E-value must be >= 1.'
                    ),
                    new Assert\LessThan(
                        value: Constants::DEFAULT_MAX_ALL_BY_ALL_BLAST_SEQUENCES + 1,
                        message: 'BLAST E-value must be <= ' . Constants::DEFAULT_MAX_ALL_BY_ALL_BLAST_SEQUENCES,
                    ),
                ],
            ]);

        if ($options['show_dev_features']) {
            $builder
                ->add('axaSearchType', EnumType::class, [
                    'label' => false,
                    'class' => AllByAllSearchType::class,
                    'required' => true,
                    'placeholder' => false,
                    'choice_label' => fn(AllByAllSearchType $choice) => $choice->getLabel(),
                ])
                ->add('axaSearchSens', EnumType::class, [
                    'label' => false,
                    'class' => AllByAllSearchSensitivity::class,
                    'required' => true,
                    'placeholder' => false,
                    'choice_label' => fn(AllByAllSearchSensitivity $choice) => $choice->getLabel(),
                ]);
        }

        $transformer = new Transformer\TaxonomySearchTransformer();

        $this->addJobNameField($builder, $options, true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
    }
}
