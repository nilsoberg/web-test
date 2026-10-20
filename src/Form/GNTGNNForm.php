<?php 

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class GNTGNNForm extends GNTForm
{
    use Partial\ConditionalParentOrFileTrait;

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
    
        $builder
			->add('cooccurrence', IntegerType::class, [
				'label' => false,
				'required' => true,
                'constraints' => [
                    new Range(
                        min: 0,
                        max: 100,
                        notInRangeMessage: 'The value must be between {{ min }} and {{ max }}.'
                    ),
                ],
            ]);
        $this->addConditionalFileOrParentFields($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => \App\Entity\GNTGNNJob::class,
        ]);
        $this->configureParentSharedDefaults($resolver);
    }
}
