<?php

namespace App\Form;

use App\Enum\SequenceType;
use App\Enum\AllByAllSearchType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CGFPIdentifyForm extends JobForm
{
    use Partial\ConditionalParentOrFileTrait, Partial\JobNameTrait, Partial\SequenceLengthTrait, Partial\UnirefVersionTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('cdhitSequenceIdentity', IntegerType::class, [
                'label' => false,
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThan(
                        value: 0,
                        message: 'CD-HIT percentage must be greater than 0.',
                    ),
                    new Assert\LessThanOrEqual(
                        value: 100,
                        message: 'CD-HIT percentage must be less than or equal to 100.',
                    ),
                ],
            ]);

        $this->addSequenceLengthFields($builder, $options);
        $this->addConditionalFileOrParentFields($builder, $options);
        $this->addUnirefVersionField($builder, $options);
        $this->addJobNameField($builder, $options, true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\CGFPIdentifyJob::class,
            'default_uniref_version' => SequenceType::UniRef90,
        ]);
        $this->configureParentSharedDefaults($resolver);
        $resolver->setAllowedTypes('default_uniref_version', [SequenceType::class, 'null']);
    }
}
