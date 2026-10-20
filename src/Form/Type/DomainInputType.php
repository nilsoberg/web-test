<?php

namespace App\Form\Type;

use App\Enum\DomainRegion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DomainInputType extends AbstractType
{
    use \App\Form\Constraint\FamilyConstraint;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', CheckboxType::class, [
                'label' => 'Sequences trimmed to the domain boundaries defined by the input family will be used for the calculations.',
                'required' => false,
            ])
            ->add('domainRegion', EnumType::class, [
                'label' => 'Region:',
                'required' => false,
                'class' => DomainRegion::class,
                'choice_label' => fn(DomainRegion $choice) => match ($choice) {
                    DomainRegion::NTerminal => 'N-Terminal',
                    DomainRegion::Domain => 'Domain [default]',
                    DomainRegion::CTerminal => 'C-Terminal',
                },
                'help' => '',
                'expanded' => true,
                'placeholder' => false,
            ]);

        if ($options['exclude_domain_family'] === false)
        {
            $builder->add('domainFamily', TextType::class, [
                'label' => false,
                'required' => false, // not required, because we want this field to be present but not required in the form
                'constraints' => [
                    $this->getFamilyConstraint('Domain Family'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'exclude_domain_family' => false,
        ]);
        $resolver->setAllowedTypes('exclude_domain_family', 'bool');
    }
}
