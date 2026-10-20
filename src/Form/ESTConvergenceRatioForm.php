<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ESTConvergenceRatioForm extends JobForm
{
    use Constraint\ThresholdValueConstraint, Partial\ConditionalParentOrFileTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('blastEValue', IntegerType::class, [
                'label' => false,
                'required' => true,
                'constraints' => $this->getThresholdValueConstraint(),
            ]);
        $this->addConditionalFileOrParentFields($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\ESTConvergenceRatioJob::class,
        ]);
        $this->configureParentSharedDefaults($resolver);
    }
}
