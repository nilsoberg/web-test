<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TaxonomyFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('predefined', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Choose a predefined filter...',
            	'choices' => [
                    'Bacteria, Archaea, Fungi' => 'bacteria_archaea_fungi',
                    'Eukaryota, no Fungi'      => 'eukaryota_no_fungi',
                    'Fungi'                    => 'fungi',
                    'Viruses'                  => 'viruses',
                    'Bacteria'                 => 'bacteria',
                    'Eukaryota'                => 'eukaryota',
                    'Archaea'                  => 'archaea',
                ],
                'attr' => [
					'data-taxonomy-filter-target' => 'predefinedSelect',
                    'data-action' => 'change->taxonomy-filter#applyPredefined'
                ],
            ])
            ->add('operator', ChoiceType::class, [
                'choices' => [
                    'Match ALL of the following (AND)' => 'AND',
                    'Match ANY of the following (OR)'  => 'OR',
                ],
                'attr' => [
                    'data-taxonomy-filter-target' => 'operatorSelect'
                ],
            ])
            ->add('conditions', CollectionType::class, [
                'entry_type' => TaxonomyFilterRowType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ]);

            $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                $data = $event->getData();
                if (empty($data['conditions'])) {
                    $event->setData(null);
                }
            });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Pass the raw JSON data to Twig so Stimulus can read it
        $view->vars['predefined_data_json'] = json_encode($options['predefined_data']);
        $view->vars['autocomplete_url'] = $options['autocomplete_url'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'predefined_data' => [],
            'autocomplete_url' => '/api/taxonomy_filter',
        ]);
    }
}
