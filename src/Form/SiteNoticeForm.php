<?php

namespace App\Form;

use App\Entity\SiteNotice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteNoticeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Notice Content (HTML tags allowed: <p>, <b>, <i>, <br>, <a>, <span> for styles)',
                'attr' => ['rows' => 8, 'class' => 'form-control font-monospace']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Banner Box Styling',
                'choices' => [
                    'Notice (Blue)' => SiteNotice::TYPE_NOTICE,
                    'Alert (Khaki)' => SiteNotice::TYPE_ALERT,
                ],
                'expanded' => true,
                'attr' => ['class' => 'mb-3']
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Make this the live banner immediately',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteNotice::class]);
    }
}
